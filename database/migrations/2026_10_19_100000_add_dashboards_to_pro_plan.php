<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Plan Pro existant : tableaux de bord commercial, financier, RH et paie (le tableau de bord IoT
 * reste hors offre, comme les capteurs). Ajout seulement, aucun réglage de l'administrateur retiré.
 */
return new class extends Migration
{
    public const array DASHBOARDS = ['sales_dashboard', 'finance_dashboard', 'hr_dashboard', 'payroll_dashboard'];

    public function up(): void
    {
        $plan = DB::table('plans')->where('slug', 'pro')->first(['id', 'features']);

        if ($plan === null) {
            return;
        }

        $features = json_decode((string) $plan->features, true);
        $features = is_array($features) ? $features : [];
        $merged = array_values(array_unique(array_merge($features, self::DASHBOARDS)));

        if (count($merged) !== count($features)) {
            DB::table('plans')->where('id', $plan->id)->update(['features' => json_encode($merged)]);
        }
    }

    public function down(): void
    {
        // Ajout de fonctionnalités : rien à défaire sans risquer de retirer un réglage de l'administrateur
    }
};
