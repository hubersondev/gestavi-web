<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farm_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('order_number', 50);
            $table->date('order_date');
            $table->date('expected_delivery_date')->nullable();
            $table->date('actual_delivery_date')->nullable();

            $table->string('status', 30)->default('draft');

            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('shipping_cost', 10, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            $table->string('payment_terms', 20)->default('on_delivery');
            $table->text('payment_terms_details')->nullable();

            $table->text('delivery_address')->nullable();
            $table->string('delivery_contact')->nullable();
            $table->string('delivery_phone', 30)->nullable();

            $table->string('supplier_quote_ref')->nullable();
            $table->string('supplier_order_ref')->nullable();

            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organisation_id', 'order_number']);
            $table->index(['organisation_id', 'order_date']);
            $table->index(['supplier_id', 'status']);
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE purchase_orders ADD CONSTRAINT purchase_orders_status_check CHECK (status IN ('draft','pending_approval','approved','sent','partially_received','received','cancelled'))");
            DB::statement("ALTER TABLE purchase_orders ADD CONSTRAINT purchase_orders_payment_terms_check CHECK (payment_terms IN ('immediate','on_delivery','net_7','net_15','net_30','net_60','custom'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
