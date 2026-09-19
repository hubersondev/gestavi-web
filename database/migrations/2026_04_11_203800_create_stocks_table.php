<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farm_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('stock_category_id')->nullable()->constrained('stock_categories')->nullOnDelete();

            $table->string('name');
            $table->string('code', 50)->nullable();
            $table->text('description')->nullable();

            $table->string('unit', 30);
            $table->decimal('quantity', 12, 2)->default(0);
            $table->decimal('min_quantity', 12, 2)->default(0);
            $table->decimal('unit_price', 12, 2)->default(0);

            $table->date('expiry_date')->nullable();
            $table->string('location')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organisation_id', 'code']);
            $table->index(['organisation_id', 'is_active']);
            $table->index(['farm_id', 'is_active']);
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE stocks ADD CONSTRAINT stocks_quantities_check CHECK (quantity >= 0 AND min_quantity >= 0 AND unit_price >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stocks');
    }
};
