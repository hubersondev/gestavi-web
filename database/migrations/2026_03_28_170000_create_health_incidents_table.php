<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flock_id')->constrained('flocks')->cascadeOnDelete();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('title');
            $table->date('incident_date');
            $table->date('detected_date')->nullable();
            $table->string('severity'); // low|medium|high|critical
            $table->string('category'); // respiratory|digestive|parasitic|viral|bacterial|nutritional|environmental|behavioral|reproductive

            // Symptômes & diagnostic
            $table->text('symptoms');
            $table->string('suspected_disease')->nullable();
            $table->string('confirmed_disease')->nullable();

            // Impact
            $table->unsignedInteger('birds_affected');
            $table->unsignedInteger('birds_dead')->default(0);

            // Traitement
            $table->text('treatment_applied')->nullable();
            $table->date('treatment_start_date')->nullable();
            $table->date('treatment_end_date')->nullable();
            $table->decimal('treatment_cost', 10, 2)->nullable();

            // Quarantaine
            $table->boolean('quarantine_applied')->default(false);
            $table->date('quarantine_start_date')->nullable();
            $table->date('quarantine_end_date')->nullable();

            // Résolution
            $table->string('status')->default('detected'); // detected|under_treatment|monitoring|resolved|chronic
            $table->date('resolution_date')->nullable();
            $table->text('resolution_notes')->nullable();

            // Amélioration continue
            $table->text('lessons_learned')->nullable();
            $table->text('preventive_measures')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['flock_id', 'incident_date']);
            $table->index(['flock_id', 'status']);
            $table->index(['category', 'severity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_incidents');
    }
};
