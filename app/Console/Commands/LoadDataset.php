<?php

namespace App\Console\Commands;

use App\Models\ChickenType;
use App\Models\Country;
use App\Models\Organisation;
use App\Models\Plan;
use App\Models\User;
use Database\Seeders\ChartAccountSeeder;
use Database\Seeders\ChickenTypeSeeder;
use Database\Seeders\CitySeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\HealthProgramSeeder;
use Database\Seeders\OrganisationPermissionSeeder;
use Database\Seeders\PayrollParameterSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\PlatformRoleSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Banc de charge : peuple la base courante d'un volume réaliste (organisations, fermes, bâtiments,
 * bandes, relevés quotidiens, clients, ventes, dépenses, trésorerie, stocks) par insertions massives,
 * puis chronomètre les traitements de fond (remise à niveau, agrégats, comptabilité, ledger).
 * Jamais en production : la base est remplie de données fictives.
 */
class LoadDataset extends Command
{
    protected $signature = 'gestavi:load-dataset
                            {--organisations=2000 : Nombre d\'organisations}
                            {--farms=5 : Fermes par organisation}
                            {--buildings=2 : Bâtiments par ferme}
                            {--days=365 : Profondeur d\'historique en jours}
                            {--accounting-sample=20 : Organisations dont la comptabilité est générée (gestavi:accounting-rebuild)}
                            {--fresh : Vider et migrer la base avant de peupler}
                            {--skip-jobs : Ne pas chronométrer les traitements de fond}';

    protected $description = 'Peuple une base de test de charge et chronomètre les traitements de fond';

    private const int CHUNK = 5000;

    /** @var array<string, float> */
    private array $timings = [];

    private string $passwordHash;

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('Refusé en production : ce banc remplit la base de données fictives.');

            return self::FAILURE;
        }

        ini_set('memory_limit', '4G');
        $this->passwordHash = Hash::make('password');

        if ($this->option('fresh')) {
            $this->time('Migration à blanc', fn () => Artisan::call('migrate:fresh', ['--force' => true]));
            $this->time('Référentiels partagés', function (): void {
                foreach ([PlatformRoleSeeder::class, OrganisationPermissionSeeder::class, CurrencySeeder::class, CountrySeeder::class, CitySeeder::class, ChickenTypeSeeder::class, PlanSeeder::class, HealthProgramSeeder::class, ChartAccountSeeder::class, PayrollParameterSeeder::class] as $seeder) {
                    Artisan::call('db:seed', ['--class' => $seeder, '--force' => true]);
                }
                User::factory()->superAdmin()->create(['email' => 'superadmin@gestavi.com']);
            });
        }

        $organisations = (int) $this->option('organisations');
        $farmsPerOrg = (int) $this->option('farms');
        $buildingsPerFarm = (int) $this->option('buildings');
        $days = (int) $this->option('days');

        $planId = Plan::query()->where('slug', 'pro')->value('id') ?? Plan::query()->value('id');
        $countryId = Country::query()->where('code', 'CI')->value('id') ?? Country::query()->value('id');
        $chickenTypeIds = ChickenType::query()->pluck('id')->all() ?: [null];

        $this->info("Génération : {$organisations} organisations × {$farmsPerOrg} fermes × {$buildingsPerFarm} bâtiments, {$days} jours d'historique.");

        $orgIds = $this->time('Organisations (Eloquent, rôles et référentiels par organisation)', fn (): array => $this->createOrganisations($organisations, $planId, $countryId));
        $owners = $this->time('Propriétaires', fn (): array => $this->createOwners($orgIds));
        $farms = $this->time('Fermes', fn (): array => $this->createFarms($orgIds, $farmsPerOrg, $chickenTypeIds));
        $buildings = $this->time('Bâtiments', fn (): array => $this->createBuildings($farms, $buildingsPerFarm));
        $flocks = $this->time('Bandes', fn (): array => $this->createFlocks($buildings, $chickenTypeIds, $days));
        $this->time('Relevés quotidiens', fn (): int => $this->createDailyRecords($flocks, $owners));
        $customers = $this->time('Clients', fn (): array => $this->createCustomers($orgIds));
        $accounts = $this->time('Comptes de trésorerie', fn (): array => $this->createAccounts($orgIds, $farms));
        $this->time('Ventes, dépenses et mouvements de trésorerie', fn (): int => $this->createSalesAndExpenses($farms, $customers, $accounts, $owners, $days));
        $this->time('Stocks et mouvements', fn (): int => $this->createStocks($farms, $owners, $days));
        $this->resetSequences();

        $this->table(['Table', 'Lignes'], collect(['organisations', 'users', 'farms', 'buildings', 'flocks', 'daily_records', 'customers', 'sales', 'sale_items', 'expenses', 'transactions', 'stocks', 'stock_movements'])
            ->map(fn (string $table): array => [$table, number_format(DB::table($table)->count(), 0, ',', ' ')])
            ->all());

        if (! $this->option('skip-jobs')) {
            $this->runBackgroundJobs($orgIds, $days);
        }

        $this->newLine();
        $this->table(['Étape', 'Durée'], collect($this->timings)->map(fn (float $seconds, string $label): array => [$label, $this->duration($seconds)])->values()->all());

        $largest = DB::table('farms')->select('organisation_id', DB::raw('count(*) as farms'))->groupBy('organisation_id')->orderByDesc('farms')->orderBy('organisation_id')->first();
        $this->info('Organisation à mesurer : '.$largest?->organisation_id.' — php artisan gestavi:load-measure --organisation='.$largest?->organisation_id);

        return self::SUCCESS;
    }

    /**
     * @return list<int>
     */
    private function createOrganisations(int $count, ?int $planId, ?int $countryId): array
    {
        $ids = [];
        $bar = $this->output->createProgressBar($count);

        for ($i = 1; $i <= $count; $i++) {
            $organisation = Organisation::create([
                'name' => 'Ferme de charge '.$i,
                'slug' => 'charge-'.$i.'-'.Str::lower(Str::random(4)),
                'email' => "charge{$i}@exemple.test",
                'country_id' => $countryId,
                'plan_id' => $planId,
                'is_active' => true,
            ]);
            $ids[] = $organisation->id;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        return $ids;
    }

    /**
     * @param  list<int>  $orgIds
     * @return array<int, int> organisation_id => user_id du propriétaire
     */
    private function createOwners(array $orgIds): array
    {
        $startId = (int) DB::table('users')->max('id') + 1;
        $rows = [];
        $owners = [];
        $now = now();

        foreach ($orgIds as $index => $orgId) {
            $userId = $startId + $index;
            $owners[$orgId] = $userId;
            $rows[] = ['id' => $userId, 'name' => 'Propriétaire '.$orgId, 'email' => "owner{$orgId}@exemple.test", 'password' => $this->passwordHash, 'type' => 'farmer', 'is_active' => true, 'email_verified_at' => $now, 'created_at' => $now, 'updated_at' => $now];
        }

        $this->insertChunks('users', $rows);

        $ownerRoles = DB::table('organisation_roles')->where('slug', 'owner')->whereIn('organisation_id', $orgIds)->pluck('id', 'organisation_id');
        $memberships = [];

        foreach ($owners as $orgId => $userId) {
            $memberships[] = ['organisation_id' => $orgId, 'user_id' => $userId, 'organisation_role_id' => $ownerRoles[$orgId], 'status' => 'active', 'joined_at' => $now, 'created_at' => $now, 'updated_at' => $now];
        }

        $this->insertChunks('organisation_users', $memberships);

        // Les propriétaires doivent avoir une 2FA configurée pour naviguer sans être renvoyés vers sa mise en place
        User::query()->whereIn('id', array_values($owners))->each(fn (User $user) => $user->saveAppAuthenticationSecret('LOAD-TEST-SECRET'));

        return $owners;
    }

    /**
     * @param  list<int>  $orgIds
     * @param  list<int|null>  $chickenTypeIds
     * @return list<array{id: int, organisation_id: int}>
     */
    private function createFarms(array $orgIds, int $perOrg, array $chickenTypeIds): array
    {
        $startId = (int) DB::table('farms')->max('id') + 1;
        $rows = [];
        $farms = [];
        $now = now();
        $id = $startId;

        foreach ($orgIds as $orgId) {
            for ($n = 1; $n <= $perOrg; $n++) {
                $rows[] = ['id' => $id, 'organisation_id' => $orgId, 'code' => "F{$n}", 'name' => "Ferme {$n} de l'organisation {$orgId}", 'capacity' => 5000, 'chicken_type_id' => $chickenTypeIds[array_rand($chickenTypeIds)], 'is_active' => true, 'created_at' => $now, 'updated_at' => $now];
                $farms[] = ['id' => $id, 'organisation_id' => $orgId];
                $id++;
            }
        }

        $this->insertChunks('farms', $rows);

        return $farms;
    }

    /**
     * @param  list<array{id: int, organisation_id: int}>  $farms
     * @return list<array{id: int, organisation_id: int, farm_id: int}>
     */
    private function createBuildings(array $farms, int $perFarm): array
    {
        $id = (int) DB::table('buildings')->max('id') + 1;
        $rows = [];
        $buildings = [];
        $now = now();

        foreach ($farms as $farm) {
            for ($n = 1; $n <= $perFarm; $n++) {
                $rows[] = ['id' => $id, 'organisation_id' => $farm['organisation_id'], 'farm_id' => $farm['id'], 'code' => "B{$n}", 'name' => "Bâtiment {$n}", 'capacity' => 2500, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now];
                $buildings[] = ['id' => $id, 'organisation_id' => $farm['organisation_id'], 'farm_id' => $farm['id']];
                $id++;
            }
        }

        $this->insertChunks('buildings', $rows);

        return $buildings;
    }

    /**
     * Deux bandes par bâtiment : une terminée dans l'année, une active.
     *
     * @param  list<array{id: int, organisation_id: int, farm_id: int}>  $buildings
     * @param  list<int|null>  $chickenTypeIds
     * @return list<array{id: int, organisation_id: int, farm_id: int, start: string, end: string, layers: bool, started: int}>
     */
    private function createFlocks(array $buildings, array $chickenTypeIds, int $days): array
    {
        $id = (int) DB::table('flocks')->max('id') + 1;
        $rows = [];
        $flocks = [];
        $now = now();
        $today = today();

        foreach ($buildings as $index => $building) {
            $layers = $index % 2 === 0;
            $completedStart = $today->copy()->subDays(min($days, 300));
            $completedEnd = $completedStart->copy()->addDays(45);
            $activeStart = $today->copy()->subDays(min($days, 120));

            foreach ([
                ['start' => $completedStart, 'end' => $completedEnd, 'status' => 'completed', 'current' => 0],
                ['start' => $activeStart, 'end' => $today, 'status' => 'active', 'current' => 1900],
            ] as $n => $spec) {
                $code = 'LOT-'.$building['id'].'-'.($n + 1);
                $rows[] = ['id' => $id, 'organisation_id' => $building['organisation_id'], 'farm_id' => $building['farm_id'], 'building_id' => $building['id'], 'chicken_type_id' => $chickenTypeIds[array_rand($chickenTypeIds)], 'code' => $code, 'name' => $code, 'quantity_started' => 2000, 'quantity_current' => $spec['current'], 'start_date' => $spec['start']->toDateString(), 'actual_end_date' => $spec['status'] === 'completed' ? $spec['end']->toDateString() : null, 'status' => $spec['status'], 'purchase_price_per_unit' => 650, 'total_purchase_price' => 1300000, 'created_at' => $now, 'updated_at' => $now];
                $flocks[] = ['id' => $id, 'organisation_id' => $building['organisation_id'], 'farm_id' => $building['farm_id'], 'start' => $spec['start']->toDateString(), 'end' => $spec['end']->toDateString(), 'layers' => $layers, 'started' => 2000];
                $id++;
            }
        }

        $this->insertChunks('flocks', $rows);

        return $flocks;
    }

    /**
     * @param  list<array{id: int, organisation_id: int, farm_id: int, start: string, end: string, layers: bool, started: int}>  $flocks
     * @param  array<int, int>  $owners
     */
    private function createDailyRecords(array $flocks, array $owners): int
    {
        $rows = [];
        $count = 0;
        $now = now();

        foreach ($flocks as $flock) {
            $day = Carbon::parse($flock['start']);
            $end = Carbon::parse($flock['end']);
            $birds = $flock['started'];
            $age = 0;

            while ($day->lessThanOrEqualTo($end)) {
                $mortality = random_int(0, 3);
                $birds = max(0, $birds - $mortality);
                $rows[] = [
                    'organisation_id' => $flock['organisation_id'], 'farm_id' => $flock['farm_id'], 'flock_id' => $flock['id'],
                    'recorded_by' => $owners[$flock['organisation_id']] ?? null, 'record_date' => $day->toDateString(), 'age_days' => $age,
                    'mortality' => $mortality, 'culls' => 0, 'birds_present' => $birds,
                    'eggs_collected' => $flock['layers'] && $age > 20 ? random_int(1400, 1800) : 0, 'eggs_broken' => $flock['layers'] ? random_int(0, 20) : 0,
                    'feed_consumed_kg' => round($birds * 0.12, 2), 'water_consumed_l' => round($birds * 0.25, 2),
                    'created_at' => $now, 'updated_at' => $now,
                ];
                $count++;

                if (count($rows) >= 2500) {
                    $this->insertChunks('daily_records', $rows);
                    $rows = [];
                }

                $day->addDay();
                $age++;
            }
        }

        $this->insertChunks('daily_records', $rows);

        return $count;
    }

    /**
     * @param  list<int>  $orgIds
     * @return array<int, list<int>> organisation_id => identifiants clients
     */
    private function createCustomers(array $orgIds): array
    {
        $id = (int) DB::table('customers')->max('id') + 1;
        $rows = [];
        $customers = [];
        $now = now();
        $types = ['particulier', 'revendeur', 'restaurant', 'hotel', 'supermarche'];

        foreach ($orgIds as $orgId) {
            for ($n = 1; $n <= 5; $n++) {
                $rows[] = ['id' => $id, 'organisation_id' => $orgId, 'code' => "CLI-{$n}", 'name' => "Client {$n} de {$orgId}", 'type' => $types[$n - 1], 'credit_limit' => 0, 'balance' => 0, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now];
                $customers[$orgId][] = $id;
                $id++;
            }
        }

        $this->insertChunks('customers', $rows);

        return $customers;
    }

    /**
     * @param  list<int>  $orgIds
     * @param  list<array{id: int, organisation_id: int}>  $farms
     * @return array<int, int> organisation_id => account_id
     */
    private function createAccounts(array $orgIds, array $farms): array
    {
        $id = (int) DB::table('accounts')->max('id') + 1;
        $rows = [];
        $accounts = [];
        $now = now();

        foreach ($orgIds as $orgId) {
            $rows[] = ['id' => $id, 'organisation_id' => $orgId, 'farm_id' => null, 'name' => 'Caisse principale', 'code' => 'CPT-001', 'type' => 'cash', 'initial_balance' => 5000000, 'current_balance' => 5000000, 'currency' => 'XOF', 'is_default' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now];
            $accounts[$orgId] = $id;
            $id++;
        }

        $this->insertChunks('accounts', $rows);

        return $accounts;
    }

    /**
     * Soixante ventes et soixante dépenses par ferme et par an, payées, avec leurs mouvements de trésorerie.
     *
     * @param  list<array{id: int, organisation_id: int}>  $farms
     * @param  array<int, list<int>>  $customers
     * @param  array<int, int>  $accounts
     * @param  array<int, int>  $owners
     */
    private function createSalesAndExpenses(array $farms, array $customers, array $accounts, array $owners, int $days): int
    {
        $saleId = (int) DB::table('sales')->max('id') + 1;
        $expenseId = (int) DB::table('expenses')->max('id') + 1;
        $now = now();
        $today = today();
        $perFarm = max(1, (int) round(60 * $days / 365));
        $sequences = [];
        $sales = $items = $expenses = $transactions = [];
        $count = 0;
        $categories = ['feed', 'medicine', 'veterinary', 'fuel', 'utilities', 'transport'];

        foreach ($farms as $farm) {
            $orgId = $farm['organisation_id'];

            for ($n = 1; $n <= $perFarm; $n++) {
                $date = $today->copy()->subDays(random_int(0, $days - 1));
                $year = $date->year;
                $sequences[$orgId][$year] = ($sequences[$orgId][$year] ?? 0) + 1;
                $seq = str_pad((string) $sequences[$orgId][$year], 5, '0', STR_PAD_LEFT);
                $quantity = random_int(5, 60);
                $total = $quantity * 3000;

                $sales[] = ['id' => $saleId, 'organisation_id' => $orgId, 'farm_id' => $farm['id'], 'customer_id' => $customers[$orgId][array_rand($customers[$orgId])], 'user_id' => $owners[$orgId] ?? null, 'account_id' => $accounts[$orgId], 'invoice_number' => "FAC-{$year}-{$seq}", 'sale_date' => $date->toDateString(), 'subtotal' => $total, 'tax_percent' => 0, 'tax_amount' => 0, 'total' => $total, 'paid' => $total, 'balance' => 0, 'status' => 'paid', 'payment_method' => 'cash', 'created_at' => $now, 'updated_at' => $now];
                $items[] = ['sale_id' => $saleId, 'product_name' => 'Plateau de 30 œufs', 'unit' => 'plateau', 'quantity' => $quantity, 'unit_price' => 3000, 'total' => $total, 'sort_order' => 1, 'is_live_birds' => false, 'created_at' => $now, 'updated_at' => $now];
                $transactions[] = ['organisation_id' => $orgId, 'farm_id' => $farm['id'], 'account_id' => $accounts[$orgId], 'user_id' => $owners[$orgId] ?? null, 'type' => 'credit', 'category' => 'sale', 'amount' => $total, 'balance_before' => 0, 'balance_after' => 0, 'reference_type' => 'App\\Models\\Sale', 'reference_id' => $saleId, 'reference_number' => "FAC-{$year}-{$seq}", 'description' => "Paiement facture FAC-{$year}-{$seq}", 'transaction_date' => $date->toDateString(), 'is_reconciled' => false, 'created_at' => $now, 'updated_at' => $now];
                $saleId++;

                $amount = random_int(20, 400) * 1000;
                $expenses[] = ['id' => $expenseId, 'organisation_id' => $orgId, 'farm_id' => $farm['id'], 'account_id' => $accounts[$orgId], 'user_id' => $owners[$orgId] ?? null, 'expense_number' => "DEP-{$year}-{$seq}", 'expense_date' => $date->toDateString(), 'paid_at' => $date, 'category' => $categories[array_rand($categories)], 'status' => 'paid', 'amount' => $amount, 'tax_percent' => 0, 'tax_amount' => 0, 'paid_amount' => $amount, 'balance' => 0, 'description' => "Dépense {$n} de la ferme {$farm['id']}", 'created_at' => $now, 'updated_at' => $now];
                $transactions[] = ['organisation_id' => $orgId, 'farm_id' => $farm['id'], 'account_id' => $accounts[$orgId], 'user_id' => $owners[$orgId] ?? null, 'type' => 'debit', 'category' => 'expense', 'amount' => $amount, 'balance_before' => 0, 'balance_after' => 0, 'reference_type' => 'App\\Models\\Expense', 'reference_id' => $expenseId, 'reference_number' => "DEP-{$year}-{$seq}", 'description' => "Paiement dépense DEP-{$year}-{$seq}", 'transaction_date' => $date->toDateString(), 'is_reconciled' => false, 'created_at' => $now, 'updated_at' => $now];
                $expenseId++;
                $count += 2;

                if (count($sales) >= 2000) {
                    $this->insertChunks('sales', $sales);
                    $this->insertChunks('sale_items', $items);
                    $this->insertChunks('expenses', $expenses);
                    $this->insertChunks('transactions', $transactions);
                    $sales = $items = $expenses = $transactions = [];
                }
            }
        }

        $this->insertChunks('sales', $sales);
        $this->insertChunks('sale_items', $items);
        $this->insertChunks('expenses', $expenses);
        $this->insertChunks('transactions', $transactions);

        return $count;
    }

    /**
     * @param  list<array{id: int, organisation_id: int}>  $farms
     * @param  array<int, int>  $owners
     */
    private function createStocks(array $farms, array $owners, int $days): int
    {
        $categoryId = (int) DB::table('stock_categories')->max('id') + 1;
        $stockId = (int) DB::table('stocks')->max('id') + 1;
        $now = now();
        $today = today();
        $categories = $stocks = $movements = [];
        $categoryByOrg = [];
        $count = 0;
        $perFarm = max(1, (int) round(50 * $days / 365));

        foreach ($farms as $farm) {
            $orgId = $farm['organisation_id'];

            if (! isset($categoryByOrg[$orgId])) {
                $categories[] = ['id' => $categoryId, 'organisation_id' => $orgId, 'name' => 'Aliment', 'slug' => 'aliment', 'type' => 'feed', 'created_at' => $now, 'updated_at' => $now];
                $categoryByOrg[$orgId] = $categoryId++;
            }

            foreach (['Aliment démarrage', 'Aliment croissance', 'Aliment ponte'] as $n => $name) {
                $stocks[] = ['id' => $stockId, 'organisation_id' => $orgId, 'farm_id' => $farm['id'], 'stock_category_id' => $categoryByOrg[$orgId], 'name' => $name, 'code' => 'STK-'.$farm['id'].'-'.($n + 1), 'unit' => 'sac', 'quantity' => 500, 'min_quantity' => 20, 'unit_price' => 18500, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now];

                for ($m = 0; $m < (int) ceil($perFarm / 3); $m++) {
                    $in = $m % 4 === 0;
                    $movements[] = ['organisation_id' => $orgId, 'farm_id' => $farm['id'], 'stock_id' => $stockId, 'user_id' => $owners[$orgId] ?? null, 'type' => $in ? 'in' : 'out', 'reason' => $in ? 'purchase' : 'consumption', 'quantity' => $in ? 100 : 10, 'unit_price' => 18500, 'total_price' => ($in ? 100 : 10) * 18500, 'movement_date' => $today->copy()->subDays(random_int(0, $days - 1))->toDateString(), 'created_at' => $now, 'updated_at' => $now];
                    $count++;
                }

                $stockId++;
            }

            if (count($movements) >= self::CHUNK) {
                $this->insertChunks('stock_categories', $categories);
                $this->insertChunks('stocks', $stocks);
                $this->insertChunks('stock_movements', $movements);
                $categories = $stocks = $movements = [];
            }
        }

        $this->insertChunks('stock_categories', $categories);
        $this->insertChunks('stocks', $stocks);
        $this->insertChunks('stock_movements', $movements);

        return $count;
    }

    /**
     * @param  list<int>  $orgIds
     */
    private function runBackgroundJobs(array $orgIds, int $days): void
    {
        $this->newLine();
        $this->info('Traitements de fond chronométrés');

        $this->time('gestavi:tenants-upgrade (toutes les organisations)', fn () => Artisan::call('gestavi:tenants-upgrade'));
        $this->time("gestavi:aggregate-farm-metrics ({$days} jours, toutes les fermes)", fn () => Artisan::call('gestavi:aggregate-farm-metrics', ['--from' => today()->subDays($days)->toDateString(), '--to' => today()->toDateString()]));

        $sample = array_slice($orgIds, 0, (int) $this->option('accounting-sample'));

        if ($sample !== []) {
            $this->time('gestavi:accounting-rebuild ('.count($sample).' organisations)', function () use ($sample): void {
                foreach ($sample as $orgId) {
                    Artisan::call('gestavi:accounting-rebuild', ['--organisation' => $orgId]);
                }
            });
            $this->time('gestavi:ledger-verify (toutes)', fn () => Artisan::call('gestavi:ledger-verify'));
            $this->time('gestavi:ledger-anchor (toutes)', fn () => Artisan::call('gestavi:ledger-anchor'));
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function insertChunks(string $table, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        // PostgreSQL limite une requête à 65 535 paramètres liés : la taille des lots dépend du nombre de colonnes
        $size = max(1, min(self::CHUNK, intdiv(60000, count($rows[0]))));

        foreach (array_chunk($rows, $size) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }

    private function resetSequences(): void
    {
        foreach (['users', 'farms', 'buildings', 'flocks', 'customers', 'accounts', 'sales', 'expenses', 'stock_categories', 'stocks'] as $table) {
            DB::statement("SELECT setval(pg_get_serial_sequence('{$table}', 'id'), COALESCE((SELECT MAX(id) FROM {$table}), 1))");
        }
    }

    private function time(string $label, callable $callback): mixed
    {
        $start = microtime(true);
        $result = $callback();
        $this->timings[$label] = microtime(true) - $start;
        $this->line(sprintf('  %-70s %s', $label, $this->duration($this->timings[$label])));

        return $result;
    }

    private function duration(float $seconds): string
    {
        return $seconds >= 60 ? sprintf('%d min %02d s', intdiv((int) $seconds, 60), (int) $seconds % 60) : sprintf('%.1f s', $seconds);
    }
}
