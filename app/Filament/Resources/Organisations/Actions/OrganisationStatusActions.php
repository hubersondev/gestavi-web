<?php

namespace App\Filament\Resources\Organisations\Actions;

use App\Enums\OrganisationStatus;
use App\Models\Organisation;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

/**
 * Changement de statut d'une organisation par l'administrateur de la plateforme : lecture seule,
 * suspension, réactivation, avec motif tracé.
 */
class OrganisationStatusActions
{
    /**
     * @return list<Action>
     */
    public static function make(): array
    {
        return [
            Action::make('setReadOnly')
                ->label('Passer en lecture seule')
                ->icon('heroicon-o-lock-closed')
                ->color('warning')
                ->visible(fn (Organisation $record): bool => $record->status !== OrganisationStatus::ReadOnly)
                ->requiresConfirmation()
                ->modalDescription(OrganisationStatus::ReadOnly->getDescription())
                ->schema([Textarea::make('reason')->label('Motif (affiché aux membres)')->rows(2)->required()])
                ->action(fn (Organisation $record, array $data) => self::apply($record, OrganisationStatus::ReadOnly, $data['reason'])),

            Action::make('suspend')
                ->label('Suspendre')
                ->icon('heroicon-o-no-symbol')
                ->color('danger')
                ->visible(fn (Organisation $record): bool => $record->status !== OrganisationStatus::Suspended)
                ->requiresConfirmation()
                ->modalDescription(OrganisationStatus::Suspended->getDescription())
                ->schema([Textarea::make('reason')->label('Motif (affiché aux membres)')->rows(2)->required()])
                ->action(fn (Organisation $record, array $data) => self::apply($record, OrganisationStatus::Suspended, $data['reason'])),

            Action::make('reactivate')
                ->label('Réactiver')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (Organisation $record): bool => $record->status !== OrganisationStatus::Active)
                ->requiresConfirmation()
                ->modalDescription('Les membres retrouvent la consultation et la saisie.')
                ->action(fn (Organisation $record) => self::apply($record, OrganisationStatus::Active, null)),
        ];
    }

    private static function apply(Organisation $record, OrganisationStatus $status, ?string $reason): void
    {
        $record->setStatus($status, $reason);

        Notification::make()
            ->title('Organisation « '.$record->name.' » : '.mb_strtolower($status->getLabel()))
            ->success()
            ->send();
    }
}
