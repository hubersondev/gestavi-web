<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_programs', function (Blueprint $table) {
            $table->id();

            // Scope organisation (null = modèle de référence système)
            $table->foreignId('organisation_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            // Scope ferme (null = applicable à toutes les fermes de l'organisation)
            $table->foreignId('farm_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            // Type de volaille ciblé (null = applicable à tous les types)
            $table->foreignId('chicken_type_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            // Traitement
            $table->string('treatment_name');
            $table->string('treatment_type', 30); // vaccin | vermifuge | anticoccidien | vitamine | antibiotique | autre

            // Planification
            $table->unsignedSmallInteger('scheduled_day'); // jour de vie de la bande (J)

            // Administration
            $table->string('administration_route', 30)->nullable(); // eau | injection | spray | oculaire | aliment
            $table->string('dosage', 100)->nullable();

            // Contraintes
            $table->unsignedSmallInteger('withdrawal_days')->default(0);
            $table->boolean('is_mandatory')->default(false);

            $table->text('notes')->nullable();
            $table->timestamps();

            // Index de recherche fréquents
            $table->index(['organisation_id', 'chicken_type_id', 'scheduled_day'], 'hp_org_bird_day_index');
            $table->index(['farm_id', 'scheduled_day'], 'hp_farm_day_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_programs');
    }
};
