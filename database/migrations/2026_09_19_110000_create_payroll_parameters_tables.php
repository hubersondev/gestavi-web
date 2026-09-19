<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Paramètres de paie versionnés (plafonds et taux CNPS, abattement et barème ITS,
 * réductions pour charges de famille). organisation_id NULL = valeurs légales par défaut,
 * remplaçables par un paramétrage propre à chaque organisation à partir d'une date d'effet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_parameters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->nullable()->constrained('organisations')->cascadeOnDelete();
            $table->date('effective_from');

            $table->decimal('cnps_ceiling', 15, 2);
            $table->decimal('cnps_employee_rate', 6, 4);
            $table->decimal('cnps_employer_pension_rate', 6, 4);
            $table->decimal('cnps_family_rate', 6, 4)->default(0);
            $table->decimal('cnps_work_accident_rate', 6, 4)->default(0);
            $table->decimal('cnps_family_ceiling', 15, 2)->nullable();
            $table->decimal('its_abatement_rate', 5, 4)->default(0);

            $table->json('family_reductions')->nullable();

            $table->string('source')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['organisation_id', 'effective_from']);
        });

        Schema::create('payroll_tax_brackets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_parameter_id')->constrained('payroll_parameters')->cascadeOnDelete();
            $table->decimal('lower_bound', 15, 2);
            $table->decimal('upper_bound', 15, 2)->nullable();
            $table->decimal('rate', 5, 4);
            $table->unsignedSmallInteger('order')->default(0);
            $table->timestamps();

            $table->index(['payroll_parameter_id', 'order']);
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE payroll_tax_brackets ADD CONSTRAINT payroll_tax_brackets_bounds_check CHECK (upper_bound IS NULL OR upper_bound > lower_bound)');
            DB::statement('ALTER TABLE payroll_tax_brackets ADD CONSTRAINT payroll_tax_brackets_rate_check CHECK (rate >= 0 AND rate <= 1)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_tax_brackets');
        Schema::dropIfExists('payroll_parameters');
    }
};
