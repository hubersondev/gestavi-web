<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chaîne stock ↔ élevage (action 34) : l'aliment d'un relevé, le produit d'une
 * vaccination et le traitement d'un incident peuvent être prélevés sur le stock ;
 * le mouvement généré est mémorisé pour les corrections et le coût de revient.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_records', function (Blueprint $table) {
            $table->foreignId('feed_stock_id')->nullable()->after('feed_consumed_kg')->constrained('stocks')->nullOnDelete();
            $table->foreignId('feed_movement_id')->nullable()->after('feed_stock_id')->constrained('stock_movements')->nullOnDelete();
        });

        Schema::table('vaccinations', function (Blueprint $table) {
            $table->foreignId('stock_id')->nullable()->after('cost')->constrained('stocks')->nullOnDelete();
            $table->decimal('stock_quantity_used', 10, 2)->nullable()->after('stock_id');
            $table->foreignId('stock_movement_id')->nullable()->after('stock_quantity_used')->constrained('stock_movements')->nullOnDelete();
        });

        Schema::table('health_incidents', function (Blueprint $table) {
            $table->foreignId('treatment_stock_id')->nullable()->after('treatment_cost')->constrained('stocks')->nullOnDelete();
            $table->decimal('treatment_stock_quantity', 10, 2)->nullable()->after('treatment_stock_id');
            $table->foreignId('treatment_movement_id')->nullable()->after('treatment_stock_quantity')->constrained('stock_movements')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('health_incidents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('treatment_movement_id');
            $table->dropConstrainedForeignId('treatment_stock_id');
            $table->dropColumn('treatment_stock_quantity');
        });

        Schema::table('vaccinations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stock_movement_id');
            $table->dropConstrainedForeignId('stock_id');
            $table->dropColumn('stock_quantity_used');
        });

        Schema::table('daily_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('feed_movement_id');
            $table->dropConstrainedForeignId('feed_stock_id');
        });
    }
};
