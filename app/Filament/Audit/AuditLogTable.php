<?php

namespace App\Filament\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Tableau du journal d'audit, partagé entre la page du panel Ferme (une organisation), la page
 * du panel Admin (toutes) et l'onglet « Historique » des fiches (un seul enregistrement).
 */
class AuditLogTable
{
    /**
     * @param  Builder<AuditLog>  $query  Périmètre déjà cloisonné (organisation, sujet)
     * @param  bool  $withSubject  Afficher le type et la référence du sujet (faux sur une fiche)
     * @param  bool  $withOrganisation  Afficher et filtrer l'organisation (panel Admin)
     * @param  callable(): array<int, string>|null  $causerOptions  Auteurs proposés au filtre
     */
    public static function configure(Table $table, ?Builder $query, bool $withSubject = true, bool $withOrganisation = false, ?callable $causerOptions = null): Table
    {
        // Sur une fiche (gestionnaire de relation), la requête vient de la relation `activities`
        if ($query !== null) {
            $table->query($query);
        }

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('causer')->when($withOrganisation, fn (Builder $query) => $query->with('organisation')))
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100])
            ->columns(array_values(array_filter([
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
                $withOrganisation ? TextColumn::make('organisation.name')
                    ->label('Structure')
                    ->placeholder('—')
                    ->searchable() : null,
                // Relation polymorphe : recherche explicite (un whereHas simple n'a pas de modèle lié)
                TextColumn::make('causer_label')
                    ->label('Par')
                    ->state(fn (AuditLog $record): string => $record->causerLabel())
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHasMorph('causer', [User::class], fn (Builder $uq) => $uq->where('name', 'ilike', '%'.$search.'%'))),
                TextColumn::make('event')
                    ->label('Action')
                    ->badge()
                    ->state(fn (AuditLog $record): string => $record->eventLabel())
                    ->color(fn (AuditLog $record): string => $record->eventColor()),
                $withSubject ? TextColumn::make('subject_type')
                    ->label('Enregistrement')
                    ->state(fn (AuditLog $record): string => $record->subjectTypeLabel()) : null,
                $withSubject ? TextColumn::make('subject_id')
                    ->label('Référence')
                    ->state(fn (AuditLog $record): string => $record->subjectLabel())
                    ->weight('medium') : null,
                TextColumn::make('changes')
                    ->label('Champs modifiés')
                    ->state(fn (AuditLog $record): string => $record->event === 'updated'
                        ? collect($record->diff())->pluck('field')->map(fn (string $f): string => str_replace('_', ' ', $f))->take(4)->implode(', ').($record->changedFieldsCount() > 4 ? '…' : '')
                        : $record->changedFieldsCount().' champ(s)')
                    ->color('gray')
                    ->wrap(),
            ])))
            ->filters(array_values(array_filter([
                SelectFilter::make('event')
                    ->label('Action')
                    ->options(['created' => 'Création', 'updated' => 'Modification', 'deleted' => 'Suppression', 'restored' => 'Restauration'])
                    ->multiple(),
                $withSubject ? SelectFilter::make('subject_type')
                    ->label('Enregistrement')
                    ->options(collect(AuditLog::SUBJECT_LABELS)->sort()->all())
                    ->multiple() : null,
                SelectFilter::make('causer_id')
                    ->label('Auteur')
                    ->options($causerOptions ?? fn (): array => User::query()->orderBy('name')->limit(200)->pluck('name', 'id')->all())
                    ->searchable(),
                Filter::make('period')
                    ->schema([
                        DatePicker::make('from')->label('Du')->native(false),
                        DatePicker::make('until')->label('Au')->native(false),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, $from) => $query->whereDate('created_at', '>=', $from))
                        ->when($data['until'] ?? null, fn (Builder $query, $until) => $query->whereDate('created_at', '<=', $until)))
                    ->indicateUsing(fn (array $data): array => array_values(array_filter([
                        ($data['from'] ?? null) ? 'Du '.Carbon::parse($data['from'])->format('d/m/Y') : null,
                        ($data['until'] ?? null) ? 'Au '.Carbon::parse($data['until'])->format('d/m/Y') : null,
                    ]))),
            ])))
            ->recordActions([
                Action::make('details')
                    ->label('Détail')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn (AuditLog $record): string => $record->eventLabel().' — '.$record->subjectTypeLabel().' '.$record->subjectLabel())
                    ->modalDescription(fn (AuditLog $record): string => 'Par '.$record->causerLabel().' le '.$record->created_at->format('d/m/Y à H:i:s'))
                    ->modalContent(fn (AuditLog $record) => view('filament.audit.changes', ['entry' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fermer'),
            ])
            ->emptyStateHeading('Aucune activité enregistrée')
            ->emptyStateDescription('Les créations, modifications et suppressions des enregistrements sensibles apparaîtront ici.');
    }
}
