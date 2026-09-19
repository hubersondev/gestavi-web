<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Country;
use Illuminate\Database\Seeder;

class CitySeeder extends Seeder
{
    /**
     * Principales villes de Côte d'Ivoire et quelques villes des pays voisins.
     */
    public function run(): void
    {
        $citiesByCountry = [
            'CI' => [
                'Abidjan', 'Yamoussoukro', 'Bouaké', 'Daloa', 'Korhogo',
                'San Pedro', 'Man', 'Gagnoa', 'Abengourou', 'Anyama',
                'Divo', 'Grand-Bassam', 'Agboville', 'Dabou', 'Bingerville',
                'Séguéla', 'Bondoukou', 'Odienné', 'Ferkessédougou', 'Soubré',
            ],
            'BF' => ['Ouagadougou', 'Bobo-Dioulasso', 'Koudougou'],
            'ML' => ['Bamako', 'Sikasso', 'Mopti'],
            'SN' => ['Dakar', 'Thiès', 'Saint-Louis'],
            'GH' => ['Accra', 'Kumasi', 'Tamale'],
        ];

        foreach ($citiesByCountry as $countryCode => $cities) {
            $country = Country::where('code', $countryCode)->first();

            if (! $country) {
                continue;
            }

            foreach ($cities as $cityName) {
                City::updateOrCreate(
                    ['name' => $cityName, 'country_id' => $country->id],
                    ['is_active' => true],
                );
            }
        }
    }
}
