<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flock_id')->constrained('flocks')->cascadeOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('record_date');
            $table->unsignedInteger('age_days')->default(0);

            // Mortalité
            $table->unsignedInteger('mortality')->default(0);
            $table->string('mortality_cause')->nullable();

            // Production d'œufs
            $table->unsignedInteger('eggs_collected')->default(0);
            $table->unsignedInteger('eggs_broken')->default(0);
            $table->unsignedInteger('eggs_sold')->default(0);

            // Consommation
            $table->decimal('feed_consumed_kg', 8, 2)->default(0);
            $table->decimal('water_consumed_l', 8, 2)->default(0);

            // Pesée
            $table->decimal('average_weight_g', 8, 2)->nullable();
            $table->unsignedInteger('birds_weighed')->nullable();

            // Santé
            $table->text('health_observations')->nullable();
            $table->text('treatments')->nullable();

            // Environnement
            $table->decimal('temperature_min', 4, 1)->nullable();
            $table->decimal('temperature_max', 4, 1)->nullable();
            $table->decimal('humidity_percent', 4, 1)->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            // Un seul enregistrement par bande par jour
            $table->unique(['flock_id', 'record_date']);
            $table->index(['flock_id', 'record_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_records');
    }
};
