<?php

namespace App\Filament\Resources\Organisations\RelationManagers;

use App\Enums\MemberStatus;
use App\Filament\Resources\FarmUsers\FarmUserResource;
use App\Models\OrganisationUser;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Membres d'une organisation : utilisateur, rôle, statut, fermes affectées, double
 * authentification, appareils mobiles ; accès à la fiche utilisateur et à ses outils de support.
 */
class MembersRelationManager extends RelationManager
{
    protected static string $relationship = 'memberships';

    protected static ?string $title = 'Membres';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['user', 'organisationRole'])
                ->withCount('farms')
                ->join('organisation_roles', 'organisation_roles.id', '=', 'organisation_users.organisation_role_id', 'left')
                ->select('organisation_users.*')
                ->orderByDesc('organisation_roles.weight'))
            ->columns([
                TextColumn::make('user.name')->label('Membre')->weight('medium')->searchable(),
                TextColumn::make('user.email')->label('Courriel')->color('gray')->searchable(),
                TextColumn::make('organisationRole.name')->label('Rôle')->badge()->color('gray')->placeholder('—'),
                TextColumn::make('status')->label('Statut')->badge(),
                TextColumn::make('farms_count')
                    ->label('Fermes')
                    ->state(fn (OrganisationUser $record): string => $record->canAccessAllFarms() ? 'Toutes' : (string) $record->farms_count),
                TextColumn::make('user.app_authentication_secret')
                    ->label('2FA')
                    ->state(fn (OrganisationUser $record): string => $record->user?->hasTwoFactorEnabled() ? 'Activée' : 'Non')
                    ->badge()
                    ->color(fn (OrganisationUser $record): string => $record->user?->hasTwoFactorEnabled() ? 'success' : 'gray'),
                TextColumn::make('devices')
                    ->label('Appareils')
                    ->state(fn (OrganisationUser $record): int => $record->user?->tokens()->count() ?? 0)
                    ->alignRight(),
                TextColumn::make('joined_at')->label('Depuis le')->date('d/m/Y')->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')->label('Statut')->options(MemberStatus::class),
            ])
            ->recordActions([
                Action::make('user')
                    ->label('Fiche et support')
                    ->icon('heroicon-o-user')
                    ->url(fn (OrganisationUser $record): string => FarmUserResource::getUrl('view', ['record' => $record->user_id])),
            ])
            ->emptyStateHeading('Aucun membre')
            ->emptyStateDescription('Une organisation sans propriétaire n\'est utilisable par personne : créez son premier utilisateur depuis Clients → Utilisateurs ferme.');
    }
}
