<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_lots', function (Blueprint $table) {
            $table->id();

            $table->foreignId('stock_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();

            $table->string('lot_number');
            $table->string('internal_lot_number')->nullable();

            $table->date('manufacturing_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->date('reception_date');
            $table->unsignedSmallInteger('shelf_life_days')->nullable();

            $table->decimal('initial_quantity', 12, 2);
            $table->decimal('current_quantity', 12, 2);
            $table->decimal('reserved_quantity', 12, 2)->default(0);

            $table->decimal('unit_price', 12, 2);
            $table->decimal('total_value', 12, 2);

            $table->string('status', 20)->default('available');
            $table->string('location')->nullable();

            $table->string('certificate_number')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['stock_id', 'lot_number']);
            $table->index(['stock_id', 'expiry_date']);
            $table->index(['status', 'expiry_date']);
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE stock_lots ADD CONSTRAINT stock_lots_status_check CHECK (status IN ('available','low','depleted','expired','blocked'))");
            DB::statement('ALTER TABLE stock_lots ADD CONSTRAINT stock_lots_quantities_check CHECK (initial_quantity >= 0 AND current_quantity >= 0 AND reserved_quantity >= 0 AND unit_price >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_lots');
    }
};
