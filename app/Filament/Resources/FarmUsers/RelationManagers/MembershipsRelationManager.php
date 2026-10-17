<?php

namespace App\Filament\Resources\FarmUsers\RelationManagers;

use App\Filament\Resources\Organisations\OrganisationResource;
use App\Models\OrganisationUser;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Appartenances d'un utilisateur ferme : organisation, rôle, statut, fermes affectées.
 */
class MembershipsRelationManager extends RelationManager
{
    protected static string $relationship = 'memberships';

    protected static ?string $title = 'Appartenances';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['organisation', 'organisationRole'])->withCount('farms'))
            ->columns([
                TextColumn::make('organisation.name')->label('Structure')->weight('medium'),
                TextColumn::make('organisationRole.name')->label('Rôle')->badge()->color('gray')->placeholder('—'),
                TextColumn::make('status')->label('Statut')->badge(),
                TextColumn::make('farms_count')
                    ->label('Fermes affectées')
                    ->state(fn (OrganisationUser $record): string => $record->canAccessAllFarms() ? 'Toutes' : (string) $record->farms_count),
                TextColumn::make('joined_at')->label('Depuis le')->date('d/m/Y')->placeholder('—'),
            ])
            ->recordActions([
                Action::make('organisation')
                    ->label('Voir la structure')
                    ->icon('heroicon-o-building-office-2')
                    ->url(fn (OrganisationUser $record): string => OrganisationResource::getUrl('view', ['record' => $record->organisation_id])),
            ])
            ->emptyStateHeading('Aucune appartenance');
    }
}
