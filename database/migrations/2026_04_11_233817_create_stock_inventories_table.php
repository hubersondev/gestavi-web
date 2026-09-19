<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_inventories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_lot_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('conducted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('adjustment_movement_id')->nullable()->constrained('stock_movements')->nullOnDelete();

            $table->string('inventory_ref', 50);
            $table->date('inventory_date');

            $table->decimal('theoretical_quantity', 12, 2);
            $table->decimal('physical_quantity', 12, 2);
            $table->decimal('difference', 12, 2);
            $table->decimal('difference_percent', 8, 2)->nullable();
            $table->decimal('unit_price', 12, 2)->nullable();
            $table->decimal('difference_value', 12, 2)->nullable();

            $table->string('status', 30)->default('draft');
            $table->string('difference_reason', 30)->nullable();
            $table->text('justification')->nullable();
            $table->text('notes')->nullable();

            $table->timestamp('validated_at')->nullable();

            $table->timestamps();

            $table->unique(['organisation_id', 'inventory_ref']);
            $table->index(['organisation_id', 'inventory_date']);
            $table->index(['stock_id', 'inventory_date']);
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE stock_inventories ADD CONSTRAINT stock_inventories_status_check CHECK (status IN ('draft','pending_validation','validated','adjusted'))");
            DB::statement("ALTER TABLE stock_inventories ADD CONSTRAINT stock_inventories_reason_check CHECK (difference_reason IS NULL OR difference_reason IN ('none','counting_error','theft','damage','expiry','unrecorded_use','unrecorded_entry','other'))");
            DB::statement('ALTER TABLE stock_inventories ADD CONSTRAINT stock_inventories_quantities_check CHECK (theoretical_quantity >= 0 AND physical_quantity >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_inventories');
    }
};
