<?php

namespace Database\Seeders;

use App\Enums\FlockStatus;
use App\Models\Building;
use App\Models\ChickenType;
use App\Models\City;
use App\Models\Country;
use App\Models\Equipment;
use App\Models\Farm;
use App\Models\Flock;
use App\Models\Organisation;
use App\Models\OrganisationUser;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Rôles et permissions plateforme
        $this->call([
            PlatformRoleSeeder::class,
        ]);

        // Permissions organisation (nécessaires avant toute création d'organisation)
        $this->call([
            OrganisationPermissionSeeder::class,
        ]);

        // Devises, pays, villes et types d'élevage
        $this->call([
            CurrencySeeder::class,
            CountrySeeder::class,
            CitySeeder::class,
            ChickenTypeSeeder::class,
        ]);

        // Plans de souscription
        $this->call([
            PlanSeeder::class,
        ]);

        // Programmes sanitaires de référence (modèles système)
        $this->call([
            HealthProgramSeeder::class,
        ]);

        // Paramètres de paie légaux par défaut (CNPS, ITS)
        $this->call([
            PayrollParameterSeeder::class,
        ]);

        // Super Admin Gestavi
        $superAdmin = User::factory()->superAdmin()->create([
            'name' => 'Super Admin',
            'email' => 'superadmin@gestavi.com',
        ]);

        // Admin Gestavi
        $admin = User::factory()->admin()->create([
            'name' => 'Admin Gestavi',
            'email' => 'admin@gestavi.com',
        ]);

        // Propriétaire d'organisation (pas de rôle plateforme)
        $owner = User::factory()->create([
            'name' => 'Jean Kouassi',
            'email' => 'jean@ferme-demo.ci',
        ]);

        // Employé
        $employee = User::factory()->create([
            'name' => 'Ama Koné',
            'email' => 'ama@ferme-demo.ci',
        ]);

        // Récupérer le pays et la ville de référence
        $coteDIvoire = Country::where('code', 'CI')->first();
        $abidjan = City::where('name', 'Abidjan')
            ->where('country_id', $coteDIvoire?->id)
            ->first();

        // Organisation de démonstration (les rôles système sont auto-créés via booted)
        $proPlan = Plan::where('slug', 'pro')->first();

        $organisation = Organisation::create([
            'name' => 'Fermes Kouassi & Fils',
            'email' => 'contact@ferme-demo.ci',
            'phone' => '+225 0700000000',
            'address' => 'Km 12, Route de Dabou',
            'country_id' => $coteDIvoire?->id,
            'city_id' => $abidjan?->id,
            'description' => 'Exploitation avicole familiale spécialisée en poulets de chair et pondeuses.',
            'is_active' => true,
            'plan_id' => $proPlan?->id,
        ]);

        // Rattacher les membres via slug de rôle
        $organisation->addMember($owner, 'owner');
        $organisation->addMember($employee, 'employee');

        // Ferme principale
        $anyama = City::where('name', 'Anyama')
            ->where('country_id', $coteDIvoire?->id)
            ->first();

        $ferme = Farm::create([
            'organisation_id' => $organisation->id,
            'code' => 'FARM-001',
            'name' => 'Ferme principale Anyama',
            'capacity' => 10000,
            'address' => 'Km 5, Route d\'Anyama',
            'city_id' => $anyama?->id,
            'latitude' => 5.4964,
            'longitude' => -4.0548,
            'phone' => '+225 0700000001',
            'email' => 'ferme-anyama@ferme-demo.ci',
            'chicken_type_id' => ChickenType::where('name', 'Mixte')->first()?->id,
            'description' => 'Site principal de production.',
            'is_active' => true,
        ]);

        // Bâtiments
        $batA1 = Building::create([
            'farm_id' => $ferme->id,
            'code' => 'BAT-001',
            'name' => 'Poulailler A1',
            'type' => 'poulailler',
            'capacity' => 5000,
            'area_sqm' => 400,
            'description' => 'Poulailler de chair — bande 1',
            'is_active' => true,
        ]);

        $batB1 = Building::create([
            'farm_id' => $ferme->id,
            'code' => 'BAT-002',
            'name' => 'Poulailler B1',
            'type' => 'poulailler',
            'capacity' => 3000,
            'area_sqm' => 250,
            'description' => 'Poulailler pondeuses',
            'is_active' => true,
        ]);

        // Équipements sur BAT-001
        Equipment::create([
            'building_id' => $batA1->id,
            'inventory_number' => 'EQP-00001',
            'name' => 'Abreuvoir automatique',
            'category' => 'abreuvement',
            'brand' => 'Plasson',
            'model' => 'Bell 360°',
            'capacity' => 10,
            'capacity_unit' => 'unités',
            'status' => 'in_service',
            'condition' => 'new',
            'purchase_date' => '2025-06-15',
            'purchase_price' => 150000,
            'current_value' => 130000,
            'lifespan_years' => 5,
            'description' => 'Abreuvoirs automatiques suspendus',
        ]);

        Equipment::create([
            'building_id' => $batA1->id,
            'inventory_number' => 'EQP-00002',
            'name' => 'Mangeoire linéaire',
            'category' => 'alimentation',
            'capacity' => 20,
            'capacity_unit' => 'unités',
            'status' => 'in_service',
            'condition' => 'good',
            'purchase_date' => '2025-04-10',
            'purchase_price' => 80000,
            'current_value' => 65000,
            'lifespan_years' => 3,
            'description' => 'Mangeoires linéaires 2m',
        ]);

        // Bandes de volailles
        $typeChair = ChickenType::where('name', 'Chair')->first();
        $typePondeuse = ChickenType::where('name', 'Pondeuse')->first();

        Flock::create([
            'building_id' => $batA1->id,
            'chicken_type_id' => $typeChair?->id,
            'code' => 'LOT-001',
            'name' => 'Bande Chair Mars 2026',
            'quantity_started' => 3000,
            'quantity_current' => 2850,
            'age_at_arrival' => 1,
            'start_date' => '2026-03-01',
            'expected_end_date' => '2026-04-15',
            'status' => FlockStatus::Active,
            'supplier_name' => 'SIPRA',
            'purchase_price_per_unit' => 450,
            'total_purchase_price' => 1350000,
            'notes' => 'Poussins Cobb 500, livrés en bon état.',
            'is_active' => true,
        ]);

        Flock::create([
            'building_id' => $batB1->id,
            'chicken_type_id' => $typePondeuse?->id,
            'code' => 'LOT-002',
            'name' => 'Bande Pondeuses Janvier 2026',
            'quantity_started' => 2000,
            'quantity_current' => 1950,
            'age_at_arrival' => 112,
            'start_date' => '2026-01-15',
            'expected_end_date' => '2027-06-15',
            'status' => FlockStatus::Active,
            'supplier_name' => 'Élevage Abidjan Poussins',
            'purchase_price_per_unit' => 3500,
            'total_purchase_price' => 7000000,
            'notes' => 'Poulettes Isa Brown 16 semaines, vaccinées.',
            'is_active' => true,
        ]);

        // Assigner la ferme principale à l'employé (restriction d'accès)
        $employeeMembership = OrganisationUser::query()
            ->where('organisation_id', $organisation->id)
            ->where('user_id', $employee->id)
            ->first();

        if ($employeeMembership) {
            $employeeMembership->farms()->attach($ferme->id);
        }
    }
}
