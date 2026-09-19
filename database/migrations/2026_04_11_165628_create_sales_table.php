<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farm_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('quote_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();

            $table->string('invoice_number', 50);
            $table->date('sale_date');
            $table->date('due_date')->nullable();

            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('paid', 12, 2)->default(0);
            $table->decimal('balance', 12, 2)->default(0);

            $table->string('status', 20)->default('draft');
            $table->string('payment_method', 20)->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organisation_id', 'invoice_number']);
            $table->index(['organisation_id', 'sale_date']);
            $table->index(['customer_id', 'status']);
            $table->index(['status', 'due_date']);
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE sales ADD CONSTRAINT sales_status_check CHECK (status IN ('draft','pending','partial','paid','cancelled'))");
            DB::statement("ALTER TABLE sales ADD CONSTRAINT sales_payment_method_check CHECK (payment_method IS NULL OR payment_method IN ('cash','orange_money','mtn_momo','wave','bank_transfer','cheque','credit'))");
        }

        // Ajout différé de la FK quotes.converted_sale_id -> sales (dépendance circulaire résolue)
        Schema::table('quotes', function (Blueprint $table): void {
            $table->foreign('converted_sale_id')
                ->references('id')
                ->on('sales')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table): void {
            $table->dropForeign(['converted_sale_id']);
        });

        Schema::dropIfExists('sales');
    }
};
