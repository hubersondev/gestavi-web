<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('renewed_by_contract_id')->nullable()->constrained('contracts')->nullOnDelete();

            $table->string('contract_number', 50)->nullable();
            $table->string('type', 20);
            $table->string('status', 20)->default('active');

            $table->date('start_date');
            $table->date('end_date')->nullable();

            $table->decimal('base_salary', 12, 2);
            $table->decimal('transport_allowance', 10, 2)->default(0);
            $table->decimal('housing_allowance', 10, 2)->default(0);
            $table->decimal('food_allowance', 10, 2)->default(0);
            $table->unsignedSmallInteger('working_hours_per_week')->default(40);
            $table->unsignedSmallInteger('annual_leave_days')->default(24);

            $table->string('position')->nullable();
            $table->string('work_location')->nullable();
            $table->text('description')->nullable();

            $table->text('termination_reason')->nullable();
            $table->date('termination_date')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
            $table->index(['status', 'end_date']);
        });

        DB::statement("ALTER TABLE contracts ADD CONSTRAINT contracts_type_check CHECK (type IN ('cdi','cdd','seasonal','intern','freelance'))");
        DB::statement("ALTER TABLE contracts ADD CONSTRAINT contracts_status_check CHECK (status IN ('active','expired','renewed','cancelled'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
