<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Suivi des vols :
 * - daily_records.thefts : sujets disparus (vol), décomptés de l'effectif mais hors mortalité ;
 * - motif « theft » sur les sorties de stock, type « theft » sur les mouvements d'équipement,
 *   catégorie « theft » sur les transactions de trésorerie (contraintes CHECK élargies).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_records', function (Blueprint $table): void {
            $table->unsignedInteger('thefts')->default(0)->after('culls');
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT IF EXISTS stock_movements_reason_check');
            DB::statement("ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_reason_check CHECK (reason IN ('purchase', 'consumption', 'sale', 'loss', 'theft', 'adjustment', 'transfer_in', 'transfer_out', 'return', 'production'))");

            DB::statement('ALTER TABLE equipment_movements DROP CONSTRAINT IF EXISTS equipment_movements_type_check');
            DB::statement("ALTER TABLE equipment_movements ADD CONSTRAINT equipment_movements_type_check CHECK (movement_type IN ('transfer', 'entry', 'exit', 'loan', 'return', 'theft', 'installation', 'decommission'))");

            DB::statement('ALTER TABLE transactions DROP CONSTRAINT IF EXISTS transactions_category_check');
            DB::statement("ALTER TABLE transactions ADD CONSTRAINT transactions_category_check CHECK (category IN ('opening', 'sale', 'payment', 'expense', 'transfer_in', 'transfer_out', 'adjustment', 'payroll', 'refund', 'theft', 'other'))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::table('transactions')->where('category', 'theft')->update(['category' => 'other']);
            DB::statement('ALTER TABLE transactions DROP CONSTRAINT IF EXISTS transactions_category_check');
            DB::statement("ALTER TABLE transactions ADD CONSTRAINT transactions_category_check CHECK (category IN ('opening', 'sale', 'payment', 'expense', 'transfer_in', 'transfer_out', 'adjustment', 'payroll', 'refund', 'other'))");

            DB::table('equipment_movements')->where('movement_type', 'theft')->update(['movement_type' => 'exit']);
            DB::statement('ALTER TABLE equipment_movements DROP CONSTRAINT IF EXISTS equipment_movements_type_check');
            DB::statement("ALTER TABLE equipment_movements ADD CONSTRAINT equipment_movements_type_check CHECK (movement_type IN ('transfer', 'entry', 'exit', 'loan', 'return', 'installation', 'decommission'))");

            DB::table('stock_movements')->where('reason', 'theft')->update(['reason' => 'loss']);
            DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT IF EXISTS stock_movements_reason_check');
            DB::statement("ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_reason_check CHECK (reason IN ('purchase', 'consumption', 'sale', 'loss', 'adjustment', 'transfer_in', 'transfer_out', 'return', 'production'))");
        }

        Schema::table('daily_records', function (Blueprint $table): void {
            $table->dropColumn('thefts');
        });
    }
};
