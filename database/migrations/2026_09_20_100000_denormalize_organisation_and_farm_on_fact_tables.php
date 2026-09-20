<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Discriminant unique (action 32) : toutes les tables de faits portent
 * `organisation_id` NOT NULL et, quand la notion existe, `farm_id`.
 *
 * - Ajout des colonnes, remplissage depuis la chaîne parente existante
 * - Clé étrangère composite (organisation_id, farm_id) → farms(organisation_id, id) :
 *   la base elle-même refuse une ferme d'une autre organisation
 * - Index composites (organisation_id, date) pour les consolidations par période
 */
return new class extends Migration
{
    /**
     * table => [ajouter organisation_id ?, ajouter farm_id ?, SQL de remplissage]
     *
     * @var array<string, array{0: bool, 1: bool, 2: string}>
     */
    private const FACT_TABLES = [
        'buildings' => [true, false,
            'UPDATE buildings b SET organisation_id = f.organisation_id FROM farms f WHERE f.id = b.farm_id AND b.organisation_id IS NULL'],
        'flocks' => [true, true,
            'UPDATE flocks x SET farm_id = b.farm_id, organisation_id = f.organisation_id FROM buildings b JOIN farms f ON f.id = b.farm_id WHERE b.id = x.building_id AND (x.organisation_id IS NULL OR x.farm_id IS NULL)'],
        'daily_records' => [true, true,
            'UPDATE daily_records x SET farm_id = fl.farm_id, organisation_id = fl.organisation_id FROM flocks fl WHERE fl.id = x.flock_id AND (x.organisation_id IS NULL OR x.farm_id IS NULL)'],
        'vaccinations' => [true, true,
            'UPDATE vaccinations x SET farm_id = fl.farm_id, organisation_id = fl.organisation_id FROM flocks fl WHERE fl.id = x.flock_id AND (x.organisation_id IS NULL OR x.farm_id IS NULL)'],
        'health_incidents' => [true, true,
            'UPDATE health_incidents x SET farm_id = fl.farm_id, organisation_id = fl.organisation_id FROM flocks fl WHERE fl.id = x.flock_id AND (x.organisation_id IS NULL OR x.farm_id IS NULL)'],
        'vet_visits' => [true, false,
            'UPDATE vet_visits x SET organisation_id = f.organisation_id FROM farms f WHERE f.id = x.farm_id AND x.organisation_id IS NULL'],
        'farm_logs' => [true, false,
            'UPDATE farm_logs x SET organisation_id = f.organisation_id FROM farms f WHERE f.id = x.farm_id AND x.organisation_id IS NULL'],
        'equipments' => [true, false,
            'UPDATE equipments x SET farm_id = COALESCE(x.farm_id, b.farm_id) FROM buildings b WHERE b.id = x.building_id AND x.farm_id IS NULL;
             UPDATE equipments x SET organisation_id = f.organisation_id FROM farms f WHERE f.id = x.farm_id AND x.organisation_id IS NULL', ],
        'equipment_movements' => [true, true,
            'UPDATE equipment_movements x SET farm_id = e.farm_id, organisation_id = e.organisation_id FROM equipments e WHERE e.id = x.equipment_id AND (x.organisation_id IS NULL OR x.farm_id IS NULL)'],
        'equipment_maintenances' => [true, true,
            'UPDATE equipment_maintenances x SET farm_id = e.farm_id, organisation_id = e.organisation_id FROM equipments e WHERE e.id = x.equipment_id AND (x.organisation_id IS NULL OR x.farm_id IS NULL)'],
        'transactions' => [true, true,
            'UPDATE transactions x SET farm_id = a.farm_id, organisation_id = a.organisation_id FROM accounts a WHERE a.id = x.account_id AND (x.organisation_id IS NULL OR x.farm_id IS NULL)'],
        'payments' => [true, true,
            'UPDATE payments x SET farm_id = s.farm_id, organisation_id = s.organisation_id FROM sales s WHERE s.id = x.sale_id AND (x.organisation_id IS NULL OR x.farm_id IS NULL)'],
        'credit_notes' => [false, true,
            'UPDATE credit_notes x SET farm_id = s.farm_id FROM sales s WHERE s.id = x.sale_id AND x.farm_id IS NULL'],
        'stock_lots' => [true, true,
            'UPDATE stock_lots x SET farm_id = s.farm_id, organisation_id = s.organisation_id FROM stocks s WHERE s.id = x.stock_id AND (x.organisation_id IS NULL OR x.farm_id IS NULL)'],
        'stock_movements' => [true, true,
            'UPDATE stock_movements x SET farm_id = s.farm_id, organisation_id = s.organisation_id FROM stocks s WHERE s.id = x.stock_id AND (x.organisation_id IS NULL OR x.farm_id IS NULL)'],
        'stock_inventories' => [false, true,
            'UPDATE stock_inventories x SET farm_id = s.farm_id FROM stocks s WHERE s.id = x.stock_id AND x.farm_id IS NULL'],
        'contracts' => [true, true,
            'UPDATE contracts x SET farm_id = e.farm_id, organisation_id = e.organisation_id FROM employees e WHERE e.id = x.employee_id AND (x.organisation_id IS NULL OR x.farm_id IS NULL)'],
        'payrolls' => [true, true,
            'UPDATE payrolls x SET farm_id = e.farm_id, organisation_id = e.organisation_id FROM employees e WHERE e.id = x.employee_id AND (x.organisation_id IS NULL OR x.farm_id IS NULL)'],
        'attendances' => [true, true,
            'UPDATE attendances x SET farm_id = e.farm_id, organisation_id = e.organisation_id FROM employees e WHERE e.id = x.employee_id AND (x.organisation_id IS NULL OR x.farm_id IS NULL)'],
        'leaves' => [true, true,
            'UPDATE leaves x SET farm_id = e.farm_id, organisation_id = e.organisation_id FROM employees e WHERE e.id = x.employee_id AND (x.organisation_id IS NULL OR x.farm_id IS NULL)'],
    ];

    /**
     * Tables portant déjà organisation_id ET farm_id : réalignement puis FK composite.
     *
     * @var list<string>
     */
    private const EXISTING_DUAL_TABLES = [
        'accounts', 'customers', 'employees', 'expenses', 'purchase_orders', 'quotes', 'sales', 'stocks', 'suppliers', 'health_programs',
    ];

    /** @var array<string, string> table => colonne de date pour l'index composite */
    private const DATE_INDEXES = [
        'daily_records' => 'record_date',
        'transactions' => 'transaction_date',
        'stock_movements' => 'movement_date',
        'vaccinations' => 'scheduled_date',
        'payments' => 'payment_date',
        'sales' => 'sale_date',
        'expenses' => 'expense_date',
        'attendances' => 'attendance_date',
        'farm_logs' => 'log_date',
    ];

    public function up(): void
    {
        // Cible des FK composites
        Schema::table('farms', fn (Blueprint $table) => $table->unique(['organisation_id', 'id'], 'farms_organisation_id_id_unique'));

        foreach (self::FACT_TABLES as $table => [$addOrganisation, $addFarm, $backfill]) {
            Schema::table($table, function (Blueprint $blueprint) use ($table, $addOrganisation, $addFarm) {
                if ($addOrganisation && ! Schema::hasColumn($table, 'organisation_id')) {
                    $blueprint->foreignId('organisation_id')->nullable()->after('id')->constrained('organisations')->cascadeOnDelete();
                }

                if ($addFarm && ! Schema::hasColumn($table, 'farm_id')) {
                    $blueprint->foreignId('farm_id')->nullable()->after('organisation_id')->constrained('farms')->nullOnDelete();
                }
            });

            foreach (array_filter(array_map('trim', explode(';', $backfill))) as $statement) {
                DB::statement($statement);
            }

            if ($addOrganisation) {
                DB::statement("ALTER TABLE {$table} ALTER COLUMN organisation_id SET NOT NULL");
            }
        }

        // Réalignement organisation ← ferme sur les tables qui portaient déjà les deux colonnes
        foreach (self::EXISTING_DUAL_TABLES as $table) {
            DB::statement("UPDATE {$table} x SET organisation_id = f.organisation_id FROM farms f WHERE f.id = x.farm_id AND x.farm_id IS NOT NULL AND (x.organisation_id IS NULL OR x.organisation_id <> f.organisation_id)");
        }

        // FK composite : (organisation_id, farm_id) doit exister dans farms(organisation_id, id)
        $dualTables = array_merge(
            self::EXISTING_DUAL_TABLES,
            array_keys(array_filter(self::FACT_TABLES, fn (array $definition): bool => $definition[1])),
            ['buildings', 'vet_visits', 'farm_logs', 'equipments'],
        );

        foreach (array_unique($dualTables) as $table) {
            if (! Schema::hasColumn($table, 'organisation_id') || ! Schema::hasColumn($table, 'farm_id')) {
                continue;
            }

            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$table}_organisation_farm_fk");
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_organisation_farm_fk FOREIGN KEY (organisation_id, farm_id) REFERENCES farms (organisation_id, id)");
        }

        foreach (self::DATE_INDEXES as $table => $column) {
            if (Schema::hasColumn($table, $column)) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index(['organisation_id', $column], "{$table}_organisation_{$column}_index"));
            }
        }
    }

    public function down(): void
    {
        foreach (self::DATE_INDEXES as $table => $column) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex("{$table}_organisation_{$column}_index"));
        }

        $dualTables = array_merge(
            self::EXISTING_DUAL_TABLES,
            array_keys(array_filter(self::FACT_TABLES, fn (array $definition): bool => $definition[1])),
            ['buildings', 'vet_visits', 'farm_logs', 'equipments'],
        );

        foreach (array_unique($dualTables) as $table) {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$table}_organisation_farm_fk");
        }

        foreach (array_reverse(self::FACT_TABLES, true) as $table => [$addOrganisation, $addFarm]) {
            Schema::table($table, function (Blueprint $blueprint) use ($addOrganisation, $addFarm) {
                if ($addFarm) {
                    $blueprint->dropConstrainedForeignId('farm_id');
                }

                if ($addOrganisation) {
                    $blueprint->dropConstrainedForeignId('organisation_id');
                }
            });
        }

        Schema::table('farms', fn (Blueprint $table) => $table->dropUnique('farms_organisation_id_id_unique'));
    }
};
