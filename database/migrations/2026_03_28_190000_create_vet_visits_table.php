<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vet_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained('farms')->cascadeOnDelete();
            $table->foreignId('flock_id')->nullable()->constrained('flocks')->nullOnDelete();
            $table->foreignId('veterinarian_id')->nullable()->constrained('veterinarians')->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('parent_visit_id')->nullable()->constrained('vet_visits')->nullOnDelete();

            // Visite
            $table->date('visit_date');
            $table->time('visit_time')->nullable();
            $table->string('visit_type'); // routine|emergency|follow_up|vaccination|diagnostic|consultation|autopsy|certification
            $table->string('reason')->nullable();

            // Examen
            $table->text('symptoms_observed')->nullable();
            $table->text('diagnosis')->nullable();
            $table->unsignedInteger('birds_examined')->nullable();
            $table->unsignedInteger('birds_affected')->nullable();
            $table->boolean('samples_taken')->default(false);
            $table->text('samples_description')->nullable();
            $table->text('lab_results')->nullable();

            // Prescriptions
            $table->text('prescriptions')->nullable();
            $table->text('recommendations')->nullable();
            $table->text('preventive_measures')->nullable();

            // Coûts
            $table->decimal('consultation_fee', 10, 2)->nullable();
            $table->decimal('medication_cost', 10, 2)->nullable();
            $table->decimal('total_cost', 10, 2)->nullable(); // auto-calculé
            $table->boolean('is_paid')->default(false);
            $table->date('paid_at')->nullable();

            // Suivi
            $table->boolean('follow_up_required')->default(false);
            $table->date('next_visit_date')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['farm_id', 'visit_date']);
            $table->index(['flock_id', 'visit_date']);
            $table->index(['veterinarian_id', 'visit_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vet_visits');
    }
};
