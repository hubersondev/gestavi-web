<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrégats quotidiens par ferme (action 39) : alimentent le tableau de bord
 * de direction sans recalculer les relevés, ventes et dépenses à chaque affichage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farm_daily_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farm_id')->constrained()->cascadeOnDelete();
            $table->date('metric_date');

            $table->unsignedInteger('active_flocks')->default(0);
            $table->unsignedInteger('birds_present')->default(0);
            $table->unsignedInteger('mortality')->default(0);
            $table->unsignedInteger('culls')->default(0);
            $table->unsignedInteger('eggs_collected')->default(0);
            $table->unsignedInteger('eggs_broken')->default(0);
            $table->decimal('feed_kg', 12, 2)->default(0);
            $table->decimal('water_l', 12, 2)->default(0);

            $table->decimal('sales_total', 15, 2)->default(0);
            $table->decimal('payments_total', 15, 2)->default(0);
            $table->decimal('expenses_total', 15, 2)->default(0);

            $table->timestamp('computed_at')->nullable();
            $table->timestamps();

            $table->unique(['farm_id', 'metric_date']);
            $table->index(['organisation_id', 'metric_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farm_daily_metrics');
    }
};
