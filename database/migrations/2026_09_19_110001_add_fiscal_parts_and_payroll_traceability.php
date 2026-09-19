<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - employees.fiscal_parts : nombre de parts fiscales (1 à 5, pas de 0,5) pour la réduction ITS.
 * - payrolls : trace du paramétrage appliqué et lien vers la dépense « salaires » créée au paiement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->decimal('fiscal_parts', 3, 1)->default(1)->after('cnps_number');
        });

        Schema::table('payrolls', function (Blueprint $table) {
            $table->foreignId('payroll_parameter_id')->nullable()->after('contract_id')
                ->constrained('payroll_parameters')->nullOnDelete();
            $table->foreignId('expense_id')->nullable()->after('paid_by')
                ->constrained('expenses')->nullOnDelete();
            $table->decimal('cnps_ceiling_applied', 15, 2)->nullable()->after('cnps_employer');
            $table->decimal('fiscal_parts_applied', 3, 1)->nullable()->after('cnps_ceiling_applied');
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE employees ADD CONSTRAINT employees_fiscal_parts_check CHECK (fiscal_parts >= 1 AND fiscal_parts <= 5 AND (fiscal_parts * 2) = FLOOR(fiscal_parts * 2))');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE employees DROP CONSTRAINT IF EXISTS employees_fiscal_parts_check');
        }

        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payroll_parameter_id');
            $table->dropConstrainedForeignId('expense_id');
            $table->dropColumn(['cnps_ceiling_applied', 'fiscal_parts_applied']);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('fiscal_parts');
        });
    }
};
