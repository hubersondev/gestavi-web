<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('stock_lot_id')->nullable()->constrained()->nullOnDelete();

            $table->string('product_name');
            $table->string('product_code', 50)->nullable();
            $table->text('description')->nullable();
            $table->string('unit', 30);

            $table->decimal('quantity_ordered', 12, 2);
            $table->decimal('quantity_received', 12, 2)->default(0);

            $table->decimal('unit_price', 12, 2);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('total', 12, 2);

            $table->date('expected_date')->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['purchase_order_id', 'status']);
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE purchase_order_items ADD CONSTRAINT purchase_order_items_status_check CHECK (status IN ('pending','partial','received','cancelled'))");
            DB::statement('ALTER TABLE purchase_order_items ADD CONSTRAINT purchase_order_items_qty_check CHECK (quantity_ordered > 0 AND quantity_received >= 0 AND quantity_received <= quantity_ordered)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
