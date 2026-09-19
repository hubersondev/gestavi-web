<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('payroll_id')->constrained('payrolls')->cascadeOnDelete();

            $table->string('type', 30);
            $table->string('label');
            $table->decimal('amount', 12, 2);
            $table->boolean('is_deduction')->default(false);
            $table->unsignedTinyInteger('sort_order')->default(0);

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['payroll_id', 'is_deduction']);
        });

        DB::statement("ALTER TABLE payroll_items ADD CONSTRAINT payroll_items_type_check CHECK (type IN ('base_salary','bonus','allowance','overtime','deduction','cnps_employee','cnps_employer','its'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_items');
    }
};
