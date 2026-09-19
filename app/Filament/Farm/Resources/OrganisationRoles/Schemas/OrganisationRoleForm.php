<?php

namespace App\Filament\Farm\Resources\OrganisationRoles\Schemas;

use App\Models\OrganisationRole;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class OrganisationRoleForm
{
    /**
     * Groupes de permissions organisés par catégorie.
     *
     * @var array<string, array<string, string>>
     */
    public const PERMISSION_GROUPS = [
        'Organisation & structure' => [
            'members'   => 'Utilisateurs',
            'farms'     => 'Fermes',
            'buildings' => 'Bâtiments',
            'roles'     => 'Rôles',
            'settings'  => 'Paramètres',
        ],
        'Élevage' => [
            'flocks'            => 'Bandes',
            'suppliers'         => 'Fournisseurs',
            'production_phases' => 'Phases de production',
        ],
        'Production & suivi' => [
            'daily_records' => 'Suivi quotidien',
            'farm_logs'     => 'Journal de ferme',
        ],
        'Santé' => [
            'health_programs'  => 'Programmes de santé',
            'vaccinations'     => 'Vaccinations',
            'health_incidents' => 'Incidents sanitaires',
            'vet_visits'       => 'Visites vétérinaires',
            'veterinarians'    => 'Vétérinaires',
        ],
        'Équipements' => [
            'equipments' => 'Équipements',
        ],
        'Ressources Humaines' => [
            'employees'   => 'Employés',
            'contracts'   => 'Contrats',
            'leaves'      => 'Congés',
            'attendances' => 'Pointages',
            'payrolls'    => 'Bulletins de paie',
        ],
    ];

    /** @var array<string, string> */
    private const ACTIONS = [
        'view'   => 'Consulter',
        'create' => 'Créer',
        'update' => 'Modifier',
        'delete' => 'Supprimer',
    ];

    /**
     * Actions supplémentaires spécifiques à certains groupes (au-delà du CRUD standard).
     *
     * @var array<string, array<string, string>>
     */
    public const EXTRA_ACTIONS = [
        'flocks' => [
            'start'    => 'Démarrer',
            'complete' => 'Clôturer',
            'cancel'   => 'Annuler',
        ],
        'farm_logs' => [
            'resolve' => 'Résoudre',
            'reopen'  => 'Rouvrir',
        ],
        'equipments' => [
            'move'        => 'Déplacer',
            'maintenance' => 'Maintenance',
            'dispose'     => 'Réformer',
        ],
        'health_incidents' => [
            'start_treatment'  => 'Démarrer traitement',
            'start_monitoring' => 'Surveillance',
            'resolve'          => 'Résoudre',
        ],
        'members' => [
            'reset_password' => 'Réinit. MDP',
            'suspend'        => 'Suspendre',
        ],
        'vaccinations' => [
            'mark_done'  => 'Marquer effectuée',
            'reschedule' => 'Reporter',
            'skip'       => 'Ignorer',
        ],
        'vet_visits' => [
            'mark_paid'        => 'Marquer payée',
            'create_follow_up' => 'Créer suivi',
        ],
        'employees' => [
            'terminate'  => 'Résilier',
            'suspend'    => 'Suspendre',
            'reactivate' => 'Réactiver',
        ],
        'contracts' => [
            'renew' => 'Renouveler',
        ],
        'leaves' => [
            'approve' => 'Approuver',
            'reject'  => 'Refuser',
        ],
        'payrolls' => [
            'validate'  => 'Valider',
            'mark_paid' => 'Marquer payé',
            'cancel'    => 'Annuler',
        ],
    ];

    public static function configure(Schema $schema): Schema
    {
        $permissionSections = [];

        foreach (self::PERMISSION_GROUPS as $categoryLabel => $groups) {
            $checkboxes = [];

            foreach ($groups as $groupSlug => $groupLabel) {
                $options = array_merge(self::ACTIONS, self::EXTRA_ACTIONS[$groupSlug] ?? []);

                $checkboxes[] = CheckboxList::make("perms_{$groupSlug}")
                    ->label($groupLabel)
                    ->options($options)
                    ->columns(2)
                    ->gridDirection('row')
                    ->afterStateHydrated(function (CheckboxList $component, $record) use ($groupSlug): void {
                        if (! $record instanceof OrganisationRole || ! $record->exists) {
                            return;
                        }

                        $actions = $record->permissions()
                            ->where('group', $groupSlug)
                            ->pluck('slug')
                            ->map(fn(string $slug) => explode('.', $slug)[1])
                            ->toArray();

                        $component->state($actions);
                    })
                    ->dehydrated(false);
            }

            $columns = match (true) {
                count($groups) >= 3 => 3,
                count($groups) === 2 => 2,
                default             => 1,
            };

            $permissionSections[] = Section::make($categoryLabel)
                ->columns($columns)
                ->schema($checkboxes)
                ->compact();
        }

        return $schema
            ->components([
                Section::make('Informations du rôle')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->label('Nom')
                            ->required()
                            ->maxLength(100)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn($set, ?string $state) => $set('slug', Str::slug($state ?? ''))),

                        TextInput::make('slug')
                            ->label('Identifiant')
                            ->required()
                            ->maxLength(80)
                            ->helperText('Identifiant technique unique du rôle'),

                        TextInput::make('weight')
                            ->label('Poids (hiérarchie)')
                            ->numeric()
                            ->required()
                            ->default(1)
                            ->minValue(1)
                            ->maxValue(10)
                            ->helperText('Plus le poids est élevé, plus le rôle a d\'autorité'),

                        TextInput::make('description')
                            ->label('Description')
                            ->maxLength(255),
                    ]),

                Section::make('Permissions')
                    ->schema($permissionSections)
                    ->columnSpanFull(),
            ]);
    }
}
