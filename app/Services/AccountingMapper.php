<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use App\Exceptions\AccountingException;
use App\Models\AccountingMapping;

/**
 * Résout une clé fonctionnelle vers un compte du plan comptable :
 * paramétrage de l'organisation, sinon paramétrage par défaut en base,
 * sinon défauts embarqués (l'application fonctionne sans seeder).
 *
 * Choix documentés :
 * - Mobile money (Orange, MTN, Moov, Wave) → 521 « Banques » : ce sont des comptes
 *   de monnaie électronique tenus par un établissement, assimilés aux comptes bancaires
 *   (et non à la caisse) ; les transferts internes passent par 585.
 * - Les dépenses « salaires » saisies hors paie sont imputées en 661.
 */
class AccountingMapper
{
    /** @var array<string, string> */
    public const DEFAULTS = [
        // Charges par catégorie de dépense
        'expense.feed' => '6021',
        'expense.medicine' => '6041',
        'expense.veterinary' => '63',
        'expense.fuel' => '605',
        'expense.utilities' => '605',
        'expense.rent' => '622',
        'expense.maintenance' => '624',
        'expense.equipment' => '605',
        'expense.transport' => '61',
        'expense.services' => '63',
        'expense.taxes' => '64',
        'expense.salaries' => '661',
        'expense.other' => '605',
        'expense.chicks' => '6011',
        // Ventes
        'sale.revenue' => '701',
        'sale.revenue.byproduct' => '7071',
        'sale.tax' => '4431',
        'sale.customer' => '411',
        // Achats
        'expense.supplier' => '401',
        'expense.tax' => '4452',
        // Paie
        'payroll.gross' => '661',
        'payroll.employer_contributions' => '664',
        'payroll.net' => '421',
        'payroll.cnps' => '431',
        'payroll.its' => '447',
        'payroll.cmu' => '433',
        'payroll.fdfp_expense' => '6413',
        'payroll.fdfp_payable' => '4423',
        // Trésorerie par type de compte financier
        'treasury.account.cash' => '571',
        'treasury.account.bank' => '521',
        'treasury.account.mobile_money' => '521',
        'treasury.account.other' => '521',
        // Modes de paiement (information, même logique que les comptes)
        'payment.method.cash' => '571',
        'payment.method.orange_money' => '521',
        'payment.method.mtn_momo' => '521',
        'payment.method.moov_money' => '521',
        'payment.method.wave' => '521',
        'payment.method.bank_transfer' => '521',
        'payment.method.cheque' => '521',
        'payment.method.credit' => '411',
        // Divers
        'transfer.internal' => '585',
        'treasury.opening' => '101',
        'treasury.adjustment' => '471',
        'treasury.other' => '471',
        'closing.result' => '131',
        'closing.retained_earnings' => '121',
        'closing.retained_loss' => '129',
    ];

    /** @var array<string, string> cache par requête : "{org}|{key}" => code */
    private array $cache = [];

    public function codeFor(string $key, ?int $organisationId = null): string
    {
        $cacheKey = ($organisationId ?? 0).'|'.$key;

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $code = AccountingMapping::query()
            ->where('mapping_key', $key)
            ->where(fn ($q) => $q->where('organisation_id', $organisationId)->orWhereNull('organisation_id'))
            ->orderByRaw('organisation_id IS NULL')
            ->value('chart_account_code');

        $code ??= self::DEFAULTS[$key] ?? throw AccountingException::unknownMapping($key);

        return $this->cache[$cacheKey] = $code;
    }

    public function expenseCode(ExpenseCategory $category, ?int $organisationId = null): string
    {
        return $this->codeFor('expense.'.$category->value, $organisationId);
    }

    public function treasuryCode(AccountType $type, ?int $organisationId = null): string
    {
        return $this->codeFor('treasury.account.'.$type->value, $organisationId);
    }

    public function paymentMethodCode(PaymentMethod $method, ?int $organisationId = null): string
    {
        return $this->codeFor('payment.method.'.$method->value, $organisationId);
    }

    public function forget(): void
    {
        $this->cache = [];
    }
}
