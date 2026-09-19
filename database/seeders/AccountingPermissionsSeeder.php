<?php

namespace Database\Seeders;

use App\Enums\PlanFeature;
use App\Models\Organisation;
use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Propage les permissions et fonctionnalités du module Comptabilité :
 * - crée les nouvelles permissions organisation (accounts, transactions, account_transfers)
 * - resynchronise les rôles système (owner/admin) des organisations existantes
 * - ajoute les features Accounting & Transfers au plan Pro existant
 *
 * Idempotent : peut être relancé sans effet de bord.
 */
class AccountingPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // 1) Créer/vérifier les permissions (réutilise le seeder global idempotent)
        $this->call(OrganisationPermissionSeeder::class);

        // 2) Resynchroniser les rôles système sur toutes les organisations existantes.
        //    createSystemRoles() utilise syncWithoutDetaching : il ajoute les nouvelles
        //    permissions aux rôles owner/admin sans toucher aux permissions custom.
        Organisation::query()->withTrashed()->each(
            fn (Organisation $organisation) => $organisation->createSystemRoles(),
        );

        // 3) Mettre à jour le plan Pro existant pour y inclure la comptabilité.
        $proPlan = Plan::where('slug', 'pro')->first();

        if ($proPlan) {
            $features = collect($proPlan->features ?? [])
                ->push(PlanFeature::Accounting->value)
                ->push(PlanFeature::Transfers->value)
                ->unique()
                ->values()
                ->all();

            $proPlan->update(['features' => $features]);
        }
    }
}
