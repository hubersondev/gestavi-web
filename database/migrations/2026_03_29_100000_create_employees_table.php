<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignId('farm_id')->nullable()->constrained('farms')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('employee_number', 50);
            $table->string('first_name');
            $table->string('last_name');
            $table->string('gender', 10)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('birth_place')->nullable();
            $table->string('nationality')->default('Ivoirienne');
            $table->string('marital_status', 20)->nullable();

            $table->string('national_id_number', 100)->nullable();
            $table->string('cnps_number', 100)->nullable();

            $table->string('phone')->nullable();
            $table->string('phone_secondary')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();

            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->string('emergency_contact_relationship')->nullable();

            $table->string('position');
            $table->string('department')->nullable();
            $table->string('status', 20)->default('active');

            $table->date('hire_date');
            $table->date('termination_date')->nullable();
            $table->text('termination_reason')->nullable();

            $table->string('bank_name')->nullable();
            $table->string('bank_account')->nullable();
            $table->string('mobile_money_number')->nullable();
            $table->string('mobile_money_operator')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organisation_id', 'employee_number']);
            $table->index(['organisation_id', 'status']);
            $table->index(['farm_id', 'status']);
        });

        DB::statement("ALTER TABLE employees ADD CONSTRAINT employees_status_check CHECK (status IN ('active','on_leave','suspended','terminated'))");
        DB::statement("ALTER TABLE employees ADD CONSTRAINT employees_gender_check CHECK (gender IN ('male','female','other'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
