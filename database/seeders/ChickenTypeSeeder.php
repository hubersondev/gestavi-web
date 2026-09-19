<?php

namespace Database\Seeders;

use App\Models\ChickenType;
use Illuminate\Database\Seeder;

class ChickenTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'Pondeuse', 'description' => 'Poules élevées pour la production d\'œufs'],
            ['name' => 'Chair', 'description' => 'Poulets élevés pour la production de viande'],
            ['name' => 'Mixte', 'description' => 'Élevage combinant pondeuses et poulets de chair'],
        ];

        foreach ($types as $type) {
            ChickenType::firstOrCreate(
                ['name' => $type['name']],
                $type,
            );
        }
    }
}
