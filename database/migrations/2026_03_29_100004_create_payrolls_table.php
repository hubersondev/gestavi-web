<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();

            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained('contracts')->nullOnDelete();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();

            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');

            $table->string('status', 20)->default('draft');

            $table->decimal('gross_salary', 12, 2)->default(0);
            $table->decimal('cnps_employee', 10, 2)->default(0);
            $table->decimal('cnps_employer', 10, 2)->default(0);
            $table->decimal('its', 10, 2)->default(0);
            $table->decimal('other_deductions', 10, 2)->default(0);
            $table->decimal('total_deductions', 12, 2)->default(0);
            $table->decimal('net_salary', 12, 2)->default(0);

            $table->unsignedSmallInteger('worked_days')->nullable();
            $table->unsignedSmallInteger('leave_days')->default(0);
            $table->decimal('overtime_hours', 6, 2)->default(0);

            $table->timestamp('validated_at')->nullable();
            $table->date('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'period_year', 'period_month']);
            $table->index(['status', 'period_year', 'period_month']);
        });

        DB::statement("ALTER TABLE payrolls ADD CONSTRAINT payrolls_status_check CHECK (status IN ('draft','validated','paid','cancelled'))");
        DB::statement("ALTER TABLE payrolls ADD CONSTRAINT payrolls_period_month_check CHECK (period_month BETWEEN 1 AND 12)");
    }

    public function down(): void
    {
        Schema::dropIfExists('payrolls');
    }
};
