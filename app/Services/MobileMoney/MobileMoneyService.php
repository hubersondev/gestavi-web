<?php

namespace App\Services\MobileMoney;

use App\Enums\AccountType;
use App\Enums\MobileMoneyOperator;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Filament\Farm\Resources\MobileMoneyTransactions\MobileMoneyTransactionResource;
use App\Models\Account;
use App\Models\MobileMoneyTransaction;
use App\Models\Organisation;
use App\Models\OrganisationUser;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\User;
use App\Notifications\OrganisationAlertNotification;
use App\Services\SaleService;
use App\Services\Sms\PhoneNumber;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Ingestion, rapprochement (automatique ou manuel) et notification des
 * encaissements mobile money.
 *
 * Résolution de l'organisation à la réception : le numéro marchand (ou
 * l'identifiant de compte marchand Wave) porté par la notification est
 * comparé aux comptes mobile money des organisations (`accounts.phone_number`
 * normalisé, ou `accounts.account_number`). À défaut, un paramètre de requête
 * `organisation=<id>` est accepté : la signature HMAC ayant déjà été vérifiée,
 * l'appelant est un opérateur de confiance. Sans résolution possible, la
 * notification est refusée (422) et journalisée.
 */
class MobileMoneyService
{
    public function __construct(private readonly SaleService $saleService) {}

    /**
     * Persiste une notification (idempotent sur opérateur + identifiant externe)
     * puis tente le rapprochement automatique. Retourne [transaction, créée ?].
     *
     * @param  array<string, mixed>  $rawPayload
     * @return array{0: MobileMoneyTransaction, 1: bool}
     */
    public function ingest(ParsedTransaction $parsed, array $rawPayload, ?int $organisationId = null): array
    {
        $existing = MobileMoneyTransaction::query()
            ->where('operator', $parsed->operator->value)
            ->where('external_id', $parsed->externalId)
            ->first();

        if ($existing) {
            return [$existing, false];
        }

        $account = $this->resolveMerchantAccount($parsed, $organisationId);
        $organisationId = $account?->organisation_id ?? $organisationId;

        if ($organisationId === null || ! Organisation::query()->whereKey($organisationId)->exists()) {
            throw MobileMoneyException::unknownOrganisation($parsed->operator->value, $parsed->merchantIdentifier);
        }

        $transaction = MobileMoneyTransaction::create([
            'organisation_id' => $organisationId,
            'farm_id' => $account?->farm_id,
            'account_id' => $account?->id,
            'operator' => $parsed->operator,
            'external_id' => $parsed->externalId,
            'direction' => $parsed->direction,
            'amount' => round($parsed->amount, 2),
            'currency' => $parsed->currency,
            'payer_phone' => PhoneNumber::normalize($parsed->payerPhone) ?? $parsed->payerPhone,
            'payer_name' => $parsed->payerName,
            'reference' => $parsed->reference,
            'occurred_at' => $parsed->occurredAt,
            'status' => TransactionStatus::Received,
            'raw_payload' => $rawPayload,
        ]);

        if ($parsed->direction === Direction::In && $parsed->successful) {
            $this->tryAutoMatch($transaction);
        }

        return [$transaction->fresh(), true];
    }

    /**
     * Rapprochement automatique : facture unique en attente du même montant (à la tolérance)
     * dont le client porte le téléphone du payeur. Sinon : notification aux responsables.
     */
    public function tryAutoMatch(MobileMoneyTransaction $transaction, bool $notifyIfUnmatched = true): bool
    {
        if (! $transaction->isReceived()) {
            return false;
        }

        $candidate = $this->autoMatchCandidate($transaction);

        if ($candidate) {
            try {
                $this->match($transaction, $candidate);

                return true;
            } catch (Throwable) {
                // Rapprochement automatique impossible (facture modifiée entre-temps) : on laisse en manuel
            }
        }

        if ($notifyIfUnmatched) {
            $this->notifyUnmatched($transaction);
        }

        return false;
    }

    public function autoMatchCandidate(MobileMoneyTransaction $transaction): ?Sale
    {
        $payerPhone = PhoneNumber::normalize($transaction->payer_phone);

        if ($payerPhone === null) {
            return null;
        }

        $matches = $this->candidateSales($transaction, exactOnly: true)
            ->filter(fn (Sale $sale): bool => $sale->customer !== null
                && (PhoneNumber::same($sale->customer->phone, $payerPhone) || PhoneNumber::same($sale->customer->phone_secondary, $payerPhone)));

        return $matches->count() === 1 ? $matches->first() : null;
    }

    /**
     * Factures en attente rapprochables : solde dû ≥ montant − tolérance
     * (exactOnly : |solde − montant| ≤ tolérance), triées par proximité de montant.
     *
     * @return EloquentCollection<int, Sale>
     */
    public function candidateSales(MobileMoneyTransaction $transaction, bool $exactOnly = false): EloquentCollection
    {
        $tolerance = (float) config('mobilemoney.match_tolerance', 1);
        $amount = (float) $transaction->amount;

        $query = Sale::query()
            ->with('customer')
            ->where('organisation_id', $transaction->organisation_id)
            ->whereIn('status', [SaleStatus::Pending->value, SaleStatus::Partial->value])
            ->where('balance', '>=', $amount - $tolerance);

        if ($exactOnly) {
            $query->where('balance', '<=', $amount + $tolerance);
        }

        return $query
            ->orderByRaw('ABS(balance - ?) ASC', [$amount])
            ->orderByDesc('sale_date')
            ->limit(50)
            ->get();
    }

    /**
     * Rapprochement : crée le paiement (méthode de l'opérateur, référence = identifiant externe,
     * date = date de l'encaissement, compte = compte mobile money marchand) et verrouille la transaction.
     */
    public function match(MobileMoneyTransaction $transaction, Sale $sale, ?int $accountId = null, ?User $by = null): Payment
    {
        if (! $transaction->isReceived()) {
            throw MobileMoneyException::alreadyProcessed($transaction->external_id);
        }

        if ((int) $sale->organisation_id !== (int) $transaction->organisation_id) {
            throw MobileMoneyException::amountMismatch($sale->invoice_number, (float) $sale->balance, (float) $transaction->amount);
        }

        $tolerance = (float) config('mobilemoney.match_tolerance', 1);
        $amount = min((float) $transaction->amount, (float) $sale->balance);

        if ($amount <= 0 || (float) $sale->balance < (float) $transaction->amount - $tolerance) {
            throw MobileMoneyException::amountMismatch($sale->invoice_number, (float) $sale->balance, (float) $transaction->amount);
        }

        $accountId ??= $transaction->account_id ?? $this->merchantAccountFor($transaction->organisation_id, $transaction->operator)?->id;

        return DB::transaction(function () use ($transaction, $sale, $amount, $accountId, $by): Payment {
            $payment = $this->saleService->recordPayment(
                sale: $sale,
                amount: $amount,
                method: self::paymentMethodFor($transaction->operator),
                accountId: $accountId,
                reference: $transaction->external_id,
                paidAt: $transaction->occurred_at,
            );

            $transaction->forceFill([
                'status' => TransactionStatus::Matched,
                'payment_id' => $payment->id,
                'account_id' => $accountId,
                'matched_by' => $by?->getKey(),
                'matched_at' => now(),
            ])->save();

            return $payment;
        });
    }

    public function ignore(MobileMoneyTransaction $transaction, ?User $by = null): void
    {
        if (! $transaction->isReceived()) {
            throw MobileMoneyException::alreadyProcessed($transaction->external_id);
        }

        $transaction->forceFill([
            'status' => TransactionStatus::Ignored,
            'matched_by' => $by?->getKey(),
            'matched_at' => now(),
        ])->save();
    }

    public static function paymentMethodFor(MobileMoneyOperator $operator): PaymentMethod
    {
        return match ($operator) {
            MobileMoneyOperator::Orange => PaymentMethod::OrangeMoney,
            MobileMoneyOperator::Mtn => PaymentMethod::MtnMomo,
            MobileMoneyOperator::Moov => PaymentMethod::MoovMoney,
            MobileMoneyOperator::Wave => PaymentMethod::Wave,
        };
    }

    /**
     * Compte mobile money marchand de l'organisation pour un opérateur.
     */
    public function merchantAccountFor(int $organisationId, MobileMoneyOperator $operator): ?Account
    {
        return Account::query()
            ->where('organisation_id', $organisationId)
            ->where('type', AccountType::MobileMoney->value)
            ->where('operator', $operator->value)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->first();
    }

    /**
     * Retrouve le compte marchand depuis l'identifiant porté par la notification
     * (numéro marchand normalisé ou identifiant de compte), éventuellement borné à une organisation.
     */
    private function resolveMerchantAccount(ParsedTransaction $parsed, ?int $organisationId): ?Account
    {
        $accounts = Account::query()
            ->where('type', AccountType::MobileMoney->value)
            ->where('operator', $parsed->operator->value)
            ->where('is_active', true)
            ->when($organisationId, fn ($q) => $q->where('organisation_id', $organisationId))
            ->get();

        if ($parsed->merchantIdentifier !== null) {
            $merchantPhone = PhoneNumber::normalize($parsed->merchantIdentifier);

            $byIdentifier = $accounts->first(fn (Account $account): bool => ($merchantPhone !== null && PhoneNumber::same($account->phone_number, $merchantPhone))
                || ($account->account_number !== null && $account->account_number === $parsed->merchantIdentifier));

            if ($byIdentifier) {
                return $byIdentifier;
            }
        }

        // Organisation connue et un seul compte pour l'opérateur : on le retient
        if ($organisationId !== null && $accounts->count() === 1) {
            return $accounts->first();
        }

        return null;
    }

    /**
     * Notification « encaissement non rapproché » aux membres à accès global.
     */
    private function notifyUnmatched(MobileMoneyTransaction $transaction): void
    {
        $recipients = $this->globalAccessRecipients($transaction->organisation_id);

        if ($recipients->isEmpty()) {
            return;
        }

        $actionUrl = null;

        try {
            $actionUrl = MobileMoneyTransactionResource::getUrl('view', ['record' => $transaction], panel: 'farm', tenant: $transaction->organisation);
        } catch (Throwable) {
            // Hors contexte de panel (tests, CLI) : pas de lien
        }

        Notification::send($recipients, new OrganisationAlertNotification(
            title: 'Encaissement mobile money non rapproché',
            lines: [sprintf(
                '• %s — %s XOF reçus de %s%s (réf. %s)',
                $transaction->operator->getLabel(),
                number_format((float) $transaction->amount, 0, ',', ' '),
                $transaction->payer_name ?? $transaction->payer_phone ?? 'inconnu',
                $transaction->reference ? ', message « '.$transaction->reference.' »' : '',
                $transaction->external_id,
            )],
            alertKey: 'mobile_money_unmatched',
            organisationId: (int) $transaction->organisation_id,
            status: 'warning',
            icon: 'heroicon-o-device-phone-mobile',
            actionLabel: 'Rapprocher',
            actionUrl: $actionUrl,
        ));
    }

    /**
     * @return Collection<int, User>
     */
    private function globalAccessRecipients(int $organisationId): Collection
    {
        return OrganisationUser::query()
            ->where('organisation_id', $organisationId)
            ->where('status', 'active')
            ->with(['user', 'organisationRole'])
            ->get()
            ->filter(fn (OrganisationUser $membership): bool => $membership->canAccessAllFarms())
            ->map(fn (OrganisationUser $membership): ?User => $membership->user)
            ->filter()
            ->unique('id')
            ->values();
    }
}
