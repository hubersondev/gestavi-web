<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chicken_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 50)->unique();
            $table->string('name')->nullable();
            $table->unsignedInteger('quantity_started');
            $table->unsignedInteger('quantity_current')->default(0);
            $table->unsignedSmallInteger('age_at_arrival')->default(0);
            $table->date('start_date');
            $table->date('expected_end_date')->nullable();
            $table->date('actual_end_date')->nullable();
            $table->string('status', 20)->default('planned');
            $table->string('supplier_name')->nullable();
            $table->decimal('purchase_price_per_unit', 12, 2)->nullable();
            $table->decimal('total_purchase_price', 14, 2)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE flocks ADD CONSTRAINT flocks_status_check CHECK (status IN ('planned', 'active', 'completed', 'cancelled'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('flocks');
    }
};
