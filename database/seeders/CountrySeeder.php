<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\Currency;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    /**
     * Pays d'Afrique de l'Ouest et quelques pays courants.
     */
    public function run(): void
    {
        // Charger les devises par code pour associer aux pays
        $currencies = Currency::pluck('id', 'code');

        $countries = [
            ['name' => "Côte d'Ivoire", 'code' => 'CI', 'code_iso3' => 'CIV', 'phone_code' => '+225', 'currency_code' => 'XOF', 'default_language' => 'fr'],
            ['name' => 'Burkina Faso', 'code' => 'BF', 'code_iso3' => 'BFA', 'phone_code' => '+226', 'currency_code' => 'XOF', 'default_language' => 'fr'],
            ['name' => 'Mali', 'code' => 'ML', 'code_iso3' => 'MLI', 'phone_code' => '+223', 'currency_code' => 'XOF', 'default_language' => 'fr'],
            ['name' => 'Sénégal', 'code' => 'SN', 'code_iso3' => 'SEN', 'phone_code' => '+221', 'currency_code' => 'XOF', 'default_language' => 'fr'],
            ['name' => 'Guinée', 'code' => 'GN', 'code_iso3' => 'GIN', 'phone_code' => '+224', 'currency_code' => 'GNF', 'default_language' => 'fr'],
            ['name' => 'Ghana', 'code' => 'GH', 'code_iso3' => 'GHA', 'phone_code' => '+233', 'currency_code' => 'GHS', 'default_language' => 'en'],
            ['name' => 'Togo', 'code' => 'TG', 'code_iso3' => 'TGO', 'phone_code' => '+228', 'currency_code' => 'XOF', 'default_language' => 'fr'],
            ['name' => 'Bénin', 'code' => 'BJ', 'code_iso3' => 'BEN', 'phone_code' => '+229', 'currency_code' => 'XOF', 'default_language' => 'fr'],
            ['name' => 'Niger', 'code' => 'NE', 'code_iso3' => 'NER', 'phone_code' => '+227', 'currency_code' => 'XOF', 'default_language' => 'fr'],
            ['name' => 'Nigeria', 'code' => 'NG', 'code_iso3' => 'NGA', 'phone_code' => '+234', 'currency_code' => 'NGN', 'default_language' => 'en'],
            ['name' => 'Cameroun', 'code' => 'CM', 'code_iso3' => 'CMR', 'phone_code' => '+237', 'currency_code' => 'XAF', 'default_language' => 'fr'],
            ['name' => 'Gabon', 'code' => 'GA', 'code_iso3' => 'GAB', 'phone_code' => '+241', 'currency_code' => 'XAF', 'default_language' => 'fr'],
            ['name' => 'Congo', 'code' => 'CG', 'code_iso3' => 'COG', 'phone_code' => '+242', 'currency_code' => 'XAF', 'default_language' => 'fr'],
            ['name' => 'RD Congo', 'code' => 'CD', 'code_iso3' => 'COD', 'phone_code' => '+243', 'currency_code' => 'CDF', 'default_language' => 'fr'],
            ['name' => 'France', 'code' => 'FR', 'code_iso3' => 'FRA', 'phone_code' => '+33', 'currency_code' => 'EUR', 'default_language' => 'fr'],
        ];

        foreach ($countries as $country) {
            $currencyCode = $country['currency_code'];
            unset($country['currency_code']);

            $country['currency_id'] = $currencies[$currencyCode] ?? null;

            Country::updateOrCreate(
                ['code' => $country['code']],
                $country,
            );
        }
    }
}
