<?php

namespace Database\Seeders;

use App\Models\OrganisationPermission;
use Illuminate\Database\Seeder;

class OrganisationPermissionSeeder extends Seeder
{
    /**
     * Crée ou vérifie les permissions d'organisation (idempotent).
     */
    public function run(): void
    {
        $groups = [
            // Organisation & structure
            'members' => 'les membres',
            'farms' => 'les fermes',
            'buildings' => 'les bâtiments',
            'roles' => 'les rôles',
            'settings' => 'les paramètres',
            // Élevage
            'flocks' => 'les bandes',
            'suppliers' => 'les fournisseurs',
            'production_phases' => 'les phases de production',
            // Production & suivi
            'daily_records' => 'le suivi quotidien',
            'farm_logs' => 'le journal de ferme',
            // Santé
            'health_programs' => 'les programmes de santé',
            'vaccinations' => 'les vaccinations',
            'health_incidents' => 'les incidents sanitaires',
            'vet_visits' => 'les visites vétérinaires',
            'veterinarians' => 'les vétérinaires',
            // Équipements
            'equipments' => 'les équipements',
            // Ressources Humaines
            'employees' => 'les employés',
            'contracts' => 'les contrats',
            'leaves' => 'les congés',
            'attendances' => 'les pointages',
            'payrolls' => 'les bulletins de paie',
            // Comptabilité
            'accounts' => 'les comptes financiers',
            'transactions' => 'les transactions',
            'account_transfers' => 'les transferts entre comptes',
            'expenses' => 'les dépenses',
            // Commercial
            'customers' => 'les clients',
            'sales' => 'les factures',
            'quotes' => 'les devis',
            'payments' => 'les paiements',
            'credit_notes' => 'les avoirs',
            // Stocks
            'stock_categories' => 'les catégories de stock',
            'stocks' => 'les produits en stock',
            'stock_lots' => 'les lots de stock',
            'stock_movements' => 'les mouvements de stock',
            'purchase_orders' => 'les bons de commande',
            'stock_inventories' => 'les inventaires physiques',
        ];

        $actions = ['view', 'create', 'update', 'delete'];
        $actionLabels = [
            'view' => 'Consulter',
            'create' => 'Créer',
            'update' => 'Modifier',
            'delete' => 'Supprimer',
        ];

        foreach ($groups as $group => $groupLabel) {
            foreach ($actions as $action) {
                $slug = "{$group}.{$action}";
                OrganisationPermission::firstOrCreate(
                    ['slug' => $slug],
                    [
                        'name' => $actionLabels[$action].' '.$groupLabel,
                        'group' => $group,
                        'description' => $actionLabels[$action].' '.$groupLabel,
                    ],
                );
            }
        }

        // Actions personnalisées par groupe
        $customActions = [
            'flocks' => [
                'start' => 'Démarrer les bandes',
                'complete' => 'Clôturer les bandes',
                'cancel' => 'Annuler les bandes',
            ],
            'farm_logs' => [
                'resolve' => 'Résoudre les entrées du journal',
                'reopen' => 'Rouvrir les entrées du journal',
            ],
            'equipments' => [
                'move' => 'Déplacer les équipements',
                'maintenance' => 'Mettre en maintenance les équipements',
                'dispose' => 'Réformer les équipements',
            ],
            'health_incidents' => [
                'start_treatment' => 'Démarrer le traitement des incidents',
                'start_monitoring' => 'Passer en surveillance les incidents',
                'resolve' => 'Résoudre les incidents sanitaires',
            ],
            'members' => [
                'reset_password' => 'Réinitialiser le mot de passe des membres',
                'suspend' => 'Suspendre/réactiver les membres',
            ],
            'vaccinations' => [
                'mark_done' => 'Marquer les vaccinations effectuées',
                'reschedule' => 'Reporter les vaccinations',
                'skip' => 'Ignorer les vaccinations',
            ],
            'vet_visits' => [
                'mark_paid' => 'Marquer les visites vétérinaires payées',
                'create_follow_up' => 'Créer un suivi de visite vétérinaire',
            ],
            'employees' => [
                'terminate' => 'Résilier les employés',
                'suspend' => 'Suspendre les employés',
                'reactivate' => 'Réactiver les employés',
            ],
            'contracts' => [
                'renew' => 'Renouveler les contrats',
            ],
            'leaves' => [
                'approve' => 'Approuver les congés',
                'reject' => 'Refuser les congés',
            ],
            'payrolls' => [
                'validate' => 'Valider les bulletins de paie',
                'mark_paid' => 'Marquer les bulletins comme payés',
                'cancel' => 'Annuler les bulletins de paie',
            ],
            'accounts' => [
                'set_default' => 'Définir un compte par défaut',
                'recalculate_balance' => 'Recalculer le solde d\'un compte',
            ],
            'transactions' => [
                'reconcile' => 'Rapprocher les transactions',
                'reverse' => 'Contrepasser les transactions',
            ],
            'account_transfers' => [
                'complete' => 'Compléter les transferts',
                'cancel' => 'Annuler les transferts',
                'reverse' => 'Contrepasser les transferts complétés',
            ],
            'sales' => [
                'record_payment' => 'Enregistrer un paiement sur une facture',
                'cancel' => 'Annuler une facture',
            ],
            'quotes' => [
                'convert' => 'Convertir un devis en facture',
            ],
            'credit_notes' => [
                'apply' => 'Imputer un avoir sur la facture liée',
                'cancel' => 'Annuler un avoir non imputé',
            ],
            'expenses' => [
                'mark_paid' => 'Enregistrer un paiement sur une dépense',
                'cancel' => 'Annuler une dépense',
            ],
            'stocks' => [
                'record_movement' => 'Enregistrer un mouvement (entrée/sortie) de stock',
            ],
            'purchase_orders' => [
                'approve' => 'Approuver un bon de commande',
                'send' => 'Envoyer un BC au fournisseur',
                'receive' => 'Réceptionner un BC',
            ],
            'stock_inventories' => [
                'validate' => 'Valider un inventaire',
                'adjust' => 'Appliquer l\'ajustement de stock',
            ],
        ];

        foreach ($customActions as $group => $actions) {
            foreach ($actions as $action => $name) {
                $slug = "{$group}.{$action}";
                OrganisationPermission::firstOrCreate(
                    ['slug' => $slug],
                    [
                        'name' => $name,
                        'group' => $group,
                        'description' => $name,
                    ],
                );
            }
        }
    }
}
