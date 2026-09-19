<?php

namespace App\Filament\Farm\Resources\Members\Tables;

use App\Enums\MemberStatus;
use App\Filament\Farm\Resources\Members\MemberResource;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Password;

class MembersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.email')
                    ->label('Adresse e-mail')
                    ->searchable(),
                TextColumn::make('user.phone')
                    ->label('Téléphone')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('organisationRole.name')
                    ->label('Rôle')
                    ->badge()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->color(fn ($state) => $state->getColor())
                    ->sortable(),
                TextColumn::make('farms.name')
                    ->label('Fermes')
                    ->badge()
                    ->placeholder('Toutes')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('joined_at')
                    ->label("Date d'adhésion")
                    ->dateTime()
                    ->sortable()
                    ->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('organisation_role_id')
                    ->label('Rôle')
                    ->relationship('organisationRole', 'name'),
                SelectFilter::make('status')
                    ->label('Statut')
                    ->options(MemberStatus::class),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()->visible(fn ($record) => MemberResource::canEdit($record)),
                Action::make('resetPassword')
                    ->label('Réinitialiser le mot de passe')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Réinitialiser le mot de passe')
                    ->modalDescription(fn ($record) => 'Un e-mail de réinitialisation sera envoyé à '.$record->user->email.'.')
                    ->visible(function ($record): bool {
                        if ($record->user_id === Filament::auth()->user()?->id) {
                            return false;
                        }

                        return MemberResource::canPerformAction('reset_password');
                    })
                    ->action(function ($record) {
                        $status = Password::sendResetLink(['email' => $record->user->email]);

                        if ($status === Password::RESET_LINK_SENT) {
                            Notification::make()
                                ->success()
                                ->title('E-mail envoyé')
                                ->body('Un lien de réinitialisation a été envoyé à '.$record->user->email.'.')
                                ->send();
                        } else {
                            Notification::make()
                                ->danger()
                                ->title('Échec de l\'envoi')
                                ->body('Impossible d\'envoyer l\'e-mail de réinitialisation.')
                                ->send();
                        }
                    }),
                Action::make('suspend')
                    ->label('Suspendre')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn ($record): bool => $record->isActive()
                        && ! $record->isOwner()
                        && $record->user_id !== Filament::auth()->user()?->id
                        && MemberResource::canPerformAction('suspend'))
                    ->action(fn ($record) => $record->suspend()),
                Action::make('reactivate')
                    ->label('Réactiver')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn ($record): bool => ($record->isSuspended() || $record->isInvited()) && MemberResource::canPerformAction('suspend'))
                    ->action(fn ($record) => $record->reactivate()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Supprimer les utilisateurs')
                        ->visible(fn () => MemberResource::canDeleteAny())
                        ->before(function (Collection $records, DeleteBulkAction $action) {
                            $currentUserId = Filament::auth()->user()?->id;

                            if ($records->contains(fn ($record) => $record->user_id === $currentUserId)) {
                                Notification::make()
                                    ->danger()
                                    ->title('Action impossible')
                                    ->body('Vous ne pouvez pas vous retirer vous-même.')
                                    ->send();

                                $action->cancel();

                                return;
                            }

                            if ($records->contains(fn ($record) => $record->isOwner())) {
                                Notification::make()
                                    ->danger()
                                    ->title('Action impossible')
                                    ->body('La sélection contient un propriétaire qui ne peut pas être retiré.')
                                    ->send();

                                $action->cancel();
                            }
                        }),
                ]),
            ]);
    }
}
