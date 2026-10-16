<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Prévisions :
 * - breed_standards : courbes de référence par souche et type de production (poids, ponte,
 *   aliment, mortalité cumulée par âge), partagées par toutes les organisations ;
 * - flock_predictions : valeurs prévues par bande, type et date cible, avec la confiance, les
 *   facteurs explicatifs et, une fois la date passée, la valeur réelle et l'écart.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('breed_standards', function (Blueprint $table): void {
            $table->id();
            $table->string('breed', 100);
            $table->string('bird_type', 20); // layer, broiler
            $table->unsignedInteger('age_days');
            $table->decimal('expected_weight_g', 8, 2)->nullable();
            $table->decimal('expected_daily_gain_g', 6, 2)->nullable();
            $table->decimal('expected_feed_intake_g', 10, 2)->nullable()->comment('cumul par sujet (chair)');
            $table->decimal('expected_feed_per_day_g', 6, 2)->nullable()->comment('par sujet et par jour');
            $table->decimal('expected_fcr', 4, 2)->nullable();
            $table->decimal('expected_laying_rate', 5, 2)->nullable();
            $table->decimal('expected_egg_weight_g', 5, 2)->nullable();
            $table->decimal('expected_mortality_cumul', 5, 2)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['breed', 'bird_type', 'age_days']);
        });

        Schema::create('flock_predictions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farm_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('flock_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30); // mortality, laying_rate, weight, feed_per_bird
            $table->date('prediction_date');
            $table->date('target_date');
            $table->unsignedInteger('target_age_days')->nullable();
            $table->decimal('predicted_value', 10, 2);
            $table->decimal('confidence', 5, 2);
            $table->decimal('standard_value', 10, 2)->nullable();
            $table->decimal('actual_value', 10, 2)->nullable();
            $table->decimal('variance', 10, 2)->nullable();
            $table->decimal('variance_percent', 8, 2)->nullable();
            $table->json('factors')->nullable();
            $table->string('model_version', 30)->nullable();
            $table->timestamps();

            $table->unique(['flock_id', 'type', 'target_date']);
            $table->index(['organisation_id', 'target_date']);
            $table->index(['farm_id', 'target_date']);
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE breed_standards ADD CONSTRAINT breed_standards_bird_type_check CHECK (bird_type IN ('layer', 'broiler'))");
            DB::statement("ALTER TABLE flock_predictions ADD CONSTRAINT flock_predictions_type_check CHECK (type IN ('mortality', 'laying_rate', 'weight', 'feed_per_bird'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('flock_predictions');
        Schema::dropIfExists('breed_standards');
    }
};
