<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vaccinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flock_id')->constrained('flocks')->cascadeOnDelete();
            $table->foreignId('health_program_id')->nullable()->constrained('health_programs')->nullOnDelete();
            $table->foreignId('administered_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('treatment_name');
            $table->string('treatment_type'); // vaccin|vermifuge|anticoccidien|vitamine|antibiotique|antiparasitaire|desinfectant|autre

            $table->date('scheduled_date');
            $table->date('administered_date')->nullable();
            $table->unsignedInteger('age_at_treatment')->nullable();

            $table->string('administration_route')->nullable(); // eau|injection|spray|oculaire|aliment|autre
            $table->string('dosage')->nullable();
            $table->string('batch_number')->nullable(); // Numéro de lot du produit
            $table->date('expiry_date')->nullable();

            $table->unsignedInteger('birds_treated')->nullable();
            $table->decimal('cost', 10, 2)->nullable();

            $table->string('status')->default('pending'); // pending|done|skipped|delayed

            $table->text('observations')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['flock_id', 'scheduled_date']);
            $table->index(['flock_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vaccinations');
    }
};
