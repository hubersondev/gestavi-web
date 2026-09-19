<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farm_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // FK vers sales ajoutée dans la migration sales (dépendance circulaire)
            $table->unsignedBigInteger('converted_sale_id')->nullable()->index();

            $table->string('quote_number', 50);
            $table->date('quote_date');
            $table->date('valid_until')->nullable();
            $table->string('reference')->nullable();

            // Informations client occasionnel (si pas de customer_id lié)
            $table->string('client_name')->nullable();
            $table->string('client_phone', 30)->nullable();
            $table->string('client_email')->nullable();
            $table->text('client_address')->nullable();

            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            $table->string('status', 20)->default('draft');

            $table->text('terms_conditions')->nullable();
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organisation_id', 'quote_number']);
            $table->index(['organisation_id', 'quote_date']);
            $table->index(['customer_id', 'status']);
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE quotes ADD CONSTRAINT quotes_status_check CHECK (status IN ('draft','sent','accepted','rejected','expired','converted'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};
