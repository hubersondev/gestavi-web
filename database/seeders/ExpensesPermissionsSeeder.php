<?php

namespace Database\Seeders;

use App\Enums\PlanFeature;
use App\Models\Organisation;
use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Propage les permissions et features du module Dépenses :
 * - crée les permissions (expenses + mark_paid / cancel)
 * - resynchronise les rôles système des organisations existantes
 * - ajoute PlanFeature::Expenses au plan Pro existant
 *
 * Idempotent — peut être relancé sans effet de bord.
 */
class ExpensesPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(OrganisationPermissionSeeder::class);

        Organisation::query()->withTrashed()->each(
            fn (Organisation $organisation) => $organisation->createSystemRoles(),
        );

        $proPlan = Plan::where('slug', 'pro')->first();

        if ($proPlan) {
            $features = collect($proPlan->features ?? [])
                ->push(PlanFeature::Expenses->value)
                ->unique()
                ->values()
                ->all();

            $proPlan->update(['features' => $features]);
        }
    }
}
