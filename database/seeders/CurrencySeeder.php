<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    /**
     * Devises courantes pour les pays d'Afrique de l'Ouest et d'Afrique centrale.
     */
    public function run(): void
    {
        $currencies = [
            ['name' => 'Franc CFA BCEAO', 'code' => 'XOF', 'symbol' => 'FCFA', 'decimal_places' => 0],
            ['name' => 'Franc CFA BEAC', 'code' => 'XAF', 'symbol' => 'FCFA', 'decimal_places' => 0],
            ['name' => 'Naira nigérian', 'code' => 'NGN', 'symbol' => '₦', 'decimal_places' => 2],
            ['name' => 'Cedi ghanéen', 'code' => 'GHS', 'symbol' => 'GH₵', 'decimal_places' => 2],
            ['name' => 'Franc guinéen', 'code' => 'GNF', 'symbol' => 'FG', 'decimal_places' => 0],
            ['name' => 'Franc congolais', 'code' => 'CDF', 'symbol' => 'FC', 'decimal_places' => 2],
            ['name' => 'Euro', 'code' => 'EUR', 'symbol' => '€', 'decimal_places' => 2],
        ];

        foreach ($currencies as $currency) {
            Currency::updateOrCreate(
                ['code' => $currency['code']],
                $currency,
            );
        }
    }
}
