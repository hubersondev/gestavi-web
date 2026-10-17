<?php

namespace App\Filament\Farm\Resources\Accounts\Actions;

use App\Exceptions\AccountException;
use App\Filament\Farm\Components\FiscalYearDatePicker;
use App\Filament\Farm\Resources\Accounts\AccountResource;
use App\Models\Account;
use App\Services\AccountService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Validation\Rule;

/**
 * Actions d'alimentation d'un compte, partagées par la fiche et la liste des comptes :
 * réapprovisionnement depuis un autre compte (transfert interne), apport de fonds extérieur
 * (exploitant, associés) et prélèvement de l'exploitant.
 */
class AccountFundingActions
{
    public static function replenish(): Action
    {
        return Action::make('replenish')
            ->label('Réapprovisionner')
            ->icon('heroicon-o-arrow-down-on-square')
            ->color('success')
            ->visible(fn (Account $record): bool => $record->is_active && AccountResource::canPerformAction('replenish'))
            ->modalHeading(fn (Account $record): string => "Réapprovisionner {$record->name}")
            ->modalDescription('Transfert interne depuis un autre compte de l\'organisation : la source est débitée du montant et des frais, ce compte est crédité du montant.')
            ->modalSubmitActionLabel('Réapprovisionner')
            ->schema(fn (Account $record): array => [
                Select::make('from_account_id')
                    ->label('Compte source')
                    ->options(fn (): array => static::sourceOptions($record))
                    // Garde-fou serveur : un compte actif de l'organisation, autre que celui réapprovisionné
                    ->rules(fn (): array => [
                        Rule::exists('accounts', 'id')
                            ->where('organisation_id', Filament::getTenant()?->getKey())
                            ->where('is_active', true)
                            ->whereNot('id', $record->id),
                    ])
                    ->required()
                    ->searchable()
                    ->preload(),

                TextInput::make('amount')
                    ->label('Montant')
                    ->numeric()
                    ->minValue(1)
                    ->suffix('XOF')
                    ->required(),

                TextInput::make('fees')
                    ->label('Frais (à la charge de la source)')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->suffix('XOF'),

                FiscalYearDatePicker::make('date')
                    ->label('Date')
                    ->required(),

                Textarea::make('notes')
                    ->label('Notes')
                    ->rows(2)
                    ->maxLength(500),
            ])
            ->action(function (Account $record, array $data): void {
                try {
                    $transfer = app(AccountService::class)->replenish(
                        $record,
                        (int) $data['from_account_id'],
                        (float) $data['amount'],
                        (float) ($data['fees'] ?? 0),
                        $data['date'] ?? null,
                        $data['notes'] ?? null,
                    );
                } catch (AccountException $e) {
                    static::failure('Réapprovisionnement impossible', $e);

                    return;
                }

                $record->refresh();

                Notification::make()
                    ->title("Compte réapprovisionné ({$transfer->reference})")
                    ->body('Nouveau solde : '.static::money((float) $record->current_balance))
                    ->success()
                    ->send();
            });
    }

    public static function contribution(): Action
    {
        return Action::make('contribution')
            ->label('Apport de fonds')
            ->icon('heroicon-o-banknotes')
            ->color('info')
            ->visible(fn (Account $record): bool => $record->is_active && AccountResource::canPerformAction('replenish'))
            ->modalHeading(fn (Account $record): string => "Apport de fonds sur {$record->name}")
            ->modalDescription('Argent apporté de l\'extérieur (exploitant, associés), sans contrepartie dans un autre compte de l\'application. Imputé au compte de l\'exploitant.')
            ->modalSubmitActionLabel('Enregistrer l\'apport')
            ->schema([
                TextInput::make('amount')
                    ->label('Montant')
                    ->numeric()
                    ->minValue(1)
                    ->suffix('XOF')
                    ->required(),

                FiscalYearDatePicker::make('date')
                    ->label('Date')
                    ->required(),

                TextInput::make('reference')
                    ->label('Référence (reçu, bordereau…)')
                    ->maxLength(100),

                TextInput::make('description')
                    ->label('Description')
                    ->placeholder('Apport de fonds de l\'exploitant')
                    ->maxLength(255),
            ])
            ->action(function (Account $record, array $data): void {
                try {
                    app(AccountService::class)->recordContribution(
                        $record,
                        (float) $data['amount'],
                        $data['description'] ?? null,
                        $data['date'] ?? null,
                        $data['reference'] ?? null,
                    );
                } catch (AccountException $e) {
                    static::failure('Apport impossible', $e);

                    return;
                }

                Notification::make()
                    ->title('Apport enregistré')
                    ->body('Nouveau solde : '.static::money((float) $record->current_balance))
                    ->success()
                    ->send();
            });
    }

    public static function withdrawal(): Action
    {
        return Action::make('withdrawal')
            ->label('Prélèvement')
            ->icon('heroicon-o-arrow-up-on-square')
            ->color('warning')
            ->visible(fn (Account $record): bool => $record->is_active && AccountResource::canPerformAction('withdraw'))
            ->modalHeading(fn (Account $record): string => "Prélèvement de l'exploitant sur {$record->name}")
            ->modalDescription('Argent retiré par l\'exploitant pour son usage personnel : ce n\'est ni une dépense de la ferme ni un transfert. Imputé au compte de l\'exploitant.')
            ->modalSubmitActionLabel('Enregistrer le prélèvement')
            ->schema(fn (Account $record): array => [
                TextInput::make('amount')
                    ->label('Montant')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue((float) $record->current_balance)
                    ->suffix('XOF')
                    ->helperText('Disponible : '.static::money((float) $record->current_balance))
                    ->required(),

                FiscalYearDatePicker::make('date')
                    ->label('Date')
                    ->required(),

                TextInput::make('reference')
                    ->label('Référence')
                    ->maxLength(100),

                TextInput::make('description')
                    ->label('Description')
                    ->placeholder('Prélèvement de l\'exploitant')
                    ->maxLength(255),
            ])
            ->action(function (Account $record, array $data): void {
                try {
                    app(AccountService::class)->recordWithdrawal(
                        $record,
                        (float) $data['amount'],
                        $data['description'] ?? null,
                        $data['date'] ?? null,
                        $data['reference'] ?? null,
                    );
                } catch (AccountException $e) {
                    static::failure('Prélèvement impossible', $e);

                    return;
                }

                Notification::make()
                    ->title('Prélèvement enregistré')
                    ->body('Nouveau solde : '.static::money((float) $record->current_balance))
                    ->success()
                    ->send();
            });
    }

    /**
     * Comptes actifs de l'organisation pouvant alimenter $account, avec leur solde.
     *
     * @return array<int, string>
     */
    private static function sourceOptions(Account $account): array
    {
        return Account::query()
            ->where('organisation_id', $account->organisation_id)
            ->whereKeyNot($account->getKey())
            ->active()
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Account $source): array => [
                $source->id => $source->name.' ('.static::money((float) $source->current_balance).')',
            ])
            ->all();
    }

    private static function money(float $amount): string
    {
        return number_format($amount, 0, ',', ' ').' XOF';
    }

    private static function failure(string $title, AccountException $exception): void
    {
        Notification::make()
            ->title($title)
            ->body($exception->getMessage())
            ->danger()
            ->persistent()
            ->send();
    }
}
