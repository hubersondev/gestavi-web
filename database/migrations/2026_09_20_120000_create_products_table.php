<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catalogue produits vendus (action 34) : plateaux d'œufs, poulets vifs ou abattus,
 * réformes, fumier… Une ligne de vente ou de devis peut référencer un produit
 * qui pré-remplit désignation, unité, prix et le drapeau « sujets vivants ».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_id')->nullable()->constrained()->nullOnDelete()
                ->comment('Produit stocké (ex. poulets abattus) : la vente pourra déstocker');
            $table->string('name');
            $table->string('code', 50)->nullable();
            $table->string('kind', 20)->default('other');
            $table->string('unit', 30)->default('unité');
            $table->decimal('default_unit_price', 12, 2)->default(0);
            $table->decimal('default_tax_percent', 5, 2)->default(0);
            $table->boolean('is_live_birds')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['organisation_id', 'name']);
            $table->index(['organisation_id', 'is_active']);
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE products ADD CONSTRAINT products_kind_check CHECK (kind IN ('eggs_tray', 'eggs_unit', 'live_bird', 'dressed_bird', 'culled_bird', 'manure', 'other'))");
        }

        Schema::table('sale_items', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('flock_id')->constrained()->nullOnDelete();
        });

        Schema::table('quote_items', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('flock_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quote_items', fn (Blueprint $table) => $table->dropConstrainedForeignId('product_id'));
        Schema::table('sale_items', fn (Blueprint $table) => $table->dropConstrainedForeignId('product_id'));
        Schema::dropIfExists('products');
    }
};
