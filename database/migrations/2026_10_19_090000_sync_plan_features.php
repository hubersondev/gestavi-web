<?php

use App\Enums\PlanFeature;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Plans déjà en base (le seeder ne modifie pas un plan existant) :
 * - Entreprise : toutes les fonctionnalités, dont la gestion des dépenses qui lui manquait ;
 * - Pro : le suivi des livraisons, exigé par l'écran Livraisons et complément des factures.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addFeatures('enterprise', array_map(fn (PlanFeature $feature): string => $feature->value, PlanFeature::cases()));
        $this->addFeatures('pro', [PlanFeature::Deliveries->value]);
    }

    public function down(): void
    {
        // Ajout de fonctionnalités : rien à défaire sans risquer de retirer un réglage de l'administrateur
    }

    /**
     * @param  list<string>  $added
     */
    private function addFeatures(string $slug, array $added): void
    {
        $plan = DB::table('plans')->where('slug', $slug)->first(['id', 'features']);

        if ($plan === null) {
            return;
        }

        $features = json_decode((string) $plan->features, true);
        $features = is_array($features) ? $features : [];
        $merged = array_values(array_unique(array_merge($features, $added)));

        if (count($merged) !== count($features)) {
            DB::table('plans')->where('id', $plan->id)->update(['features' => json_encode($merged)]);
        }
    }
};
