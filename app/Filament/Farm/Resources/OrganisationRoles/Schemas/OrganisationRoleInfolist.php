<?php

namespace App\Filament\Farm\Resources\OrganisationRoles\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrganisationRoleInfolist
{
    /** @var array<string, string> */
    private const ACTION_LABELS = [
        'view' => 'Consulter',
        'create' => 'Créer',
        'update' => 'Modifier',
        'delete' => 'Supprimer',
        // Bandes
        'start' => 'Démarrer',
        'complete' => 'Clôturer',
        'cancel' => 'Annuler',
        // Journal de ferme
        'resolve' => 'Résoudre',
        'reopen' => 'Rouvrir',
        // Équipements
        'move' => 'Déplacer',
        'maintenance' => 'Maintenance',
        'dispose' => 'Réformer',
        // Incidents sanitaires
        'start_treatment' => 'Démarrer traitement',
        'start_monitoring' => 'Surveillance',
        // Membres
        'reset_password' => 'Réinit. MDP',
        'suspend' => 'Suspendre',
        // Vaccinations
        'mark_done' => 'Marquer effectuée',
        'reschedule' => 'Reporter',
        'skip' => 'Ignorer',
        // Visites vétérinaires
        'mark_paid' => 'Marquer payée',
        'create_follow_up' => 'Créer suivi',
        // Employés
        'terminate' => 'Résilier',
        'reactivate' => 'Réactiver',
        // Contrats
        'renew' => 'Renouveler',
        // Congés
        'approve' => 'Approuver',
        'reject' => 'Refuser',
        // Bulletins de paie
        'validate' => 'Valider',
        'cancel' => 'Annuler',
    ];

    /** @var array<string, string> */
    private const ACTION_COLORS = [
        'view' => 'gray',
        'create' => 'success',
        'update' => 'warning',
        'delete' => 'danger',
        // Actions personnalisées
        'start' => 'success',
        'complete' => 'success',
        'cancel' => 'danger',
        'resolve' => 'success',
        'reopen' => 'warning',
        'move' => 'info',
        'maintenance' => 'warning',
        'dispose' => 'danger',
        'start_treatment' => 'info',
        'start_monitoring' => 'primary',
        'reset_password' => 'warning',
        'suspend' => 'danger',
        'mark_done' => 'success',
        'reschedule' => 'warning',
        'skip' => 'danger',
        'mark_paid' => 'success',
        'create_follow_up' => 'info',
        // Employés
        'terminate' => 'danger',
        'reactivate' => 'success',
        // Contrats
        'renew' => 'info',
        // Congés
        'approve' => 'success',
        'reject' => 'danger',
        // Bulletins de paie
        'validate' => 'success',
        'cancel' => 'danger',
    ];

    public static function configure(Schema $schema): Schema
    {
        $permissionSections = [];

        foreach (OrganisationRoleForm::PERMISSION_GROUPS as $categoryLabel => $groups) {
            $entries = [];

            foreach ($groups as $groupSlug => $groupLabel) {
                $entries[] = TextEntry::make("perm_{$groupSlug}")
                    ->label($groupLabel)
                    ->state(function ($record) use ($groupSlug): array {
                        if (! $record?->exists) {
                            return [];
                        }

                        return $record->permissions()
                            ->where('group', $groupSlug)
                            ->pluck('slug')
                            ->map(fn (string $slug) => explode('.', $slug)[1])
                            ->toArray();
                    })
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::ACTION_LABELS[$state] ?? $state)
                    ->color(fn (string $state): string => self::ACTION_COLORS[$state] ?? 'gray')
                    ->placeholder('—');
            }

            $columns = match (true) {
                count($groups) >= 3 => 3,
                count($groups) === 2 => 2,
                default => 1,
            };

            $permissionSections[] = Section::make($categoryLabel)
                ->columns($columns)
                ->schema($entries)
                ->compact();
        }

        return $schema
            ->components([
                Section::make('Informations du rôle')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('name')
                            ->label('Nom'),

                        TextEntry::make('slug')
                            ->label('Identifiant')
                            ->badge()
                            ->color('gray'),

                        TextEntry::make('weight')
                            ->label('Poids (hiérarchie)'),

                        TextEntry::make('description')
                            ->label('Description')
                            ->placeholder('-'),

                        TextEntry::make('members_count')
                            ->label('Nombre de membres')
                            ->state(fn ($record) => $record->members()->count()),
                    ]),

                Section::make('Permissions')
                    ->schema($permissionSections)
                    ->columnSpanFull(),
            ]);
    }
}
