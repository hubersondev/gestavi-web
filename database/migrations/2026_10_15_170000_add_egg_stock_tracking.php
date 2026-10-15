<?php

use App\Models\Stock;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Suivi du stock d'œufs : les relevés du jour alimentent un stock « Œufs » par ferme (catégorie
 * de type production), les ventes de produits suivis en sortent (plateau = 30 œufs), et une
 * vente au-delà du disponible est refusée.
 *
 * - stocks.kind : marque le stock d'œufs de chaque ferme ('eggs')
 * - products.stock_units_per_unit : unités de stock sorties par unité vendue (30 pour un plateau)
 * - contraintes : type de catégorie 'production', motif de mouvement 'production'
 * - reprise : un stock d'œufs par ferme existante, initialisé depuis les relevés et ventes passés
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stocks', function (Blueprint $table): void {
            $table->string('kind', 30)->nullable()->after('stock_category_id');
            $table->index(['farm_id', 'kind']);
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->decimal('stock_units_per_unit', 10, 2)->default(1)->after('unit');
        });

        DB::table('products')->where('kind', 'eggs_tray')->update(['stock_units_per_unit' => 30]);

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE stock_categories DROP CONSTRAINT IF EXISTS stock_categories_type_check');
            DB::statement("ALTER TABLE stock_categories ADD CONSTRAINT stock_categories_type_check CHECK (type IN ('feed', 'medicine', 'material', 'packaging', 'production', 'other'))");

            DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT IF EXISTS stock_movements_reason_check');
            DB::statement("ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_reason_check CHECK (reason IN ('purchase', 'consumption', 'sale', 'loss', 'adjustment', 'transfer_in', 'transfer_out', 'return', 'production'))");
        }

        Stock::ensureEggStocksForAllFarms();
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::table('stock_movements')->where('reason', 'production')->update(['reason' => 'adjustment']);
            DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT IF EXISTS stock_movements_reason_check');
            DB::statement("ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_reason_check CHECK (reason IN ('purchase', 'consumption', 'sale', 'loss', 'adjustment', 'transfer_in', 'transfer_out', 'return'))");

            DB::table('stock_categories')->where('type', 'production')->update(['type' => 'other']);
            DB::statement('ALTER TABLE stock_categories DROP CONSTRAINT IF EXISTS stock_categories_type_check');
            DB::statement("ALTER TABLE stock_categories ADD CONSTRAINT stock_categories_type_check CHECK (type IN ('feed', 'medicine', 'material', 'packaging', 'other'))");
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('stock_units_per_unit');
        });

        Schema::table('stocks', function (Blueprint $table): void {
            $table->dropIndex(['farm_id', 'kind']);
            $table->dropColumn('kind');
        });
    }
};
