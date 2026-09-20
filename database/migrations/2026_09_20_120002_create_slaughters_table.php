<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Abattages (action 34) : sortie de sujets d'une bande vers un stock de
 * carcasses ; l'effectif dérivé de la bande en tient compte.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slaughters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farm_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('flock_id')->constrained()->restrictOnDelete();
            $table->foreignId('destination_stock_id')->nullable()->constrained('stocks')->nullOnDelete();
            $table->foreignId('stock_movement_id')->nullable()->constrained('stock_movements')->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->date('slaughter_date');
            $table->unsignedInteger('birds_count');
            $table->decimal('live_weight_kg', 10, 2)->nullable();
            $table->decimal('carcass_weight_kg', 10, 2)->nullable();
            $table->decimal('yield_percent', 5, 2)->nullable()->comment('Rendement carcasse = poids carcasse / poids vif × 100');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['organisation_id', 'slaughter_date']);
            $table->index(['flock_id', 'slaughter_date']);
        });

        DB::statement('ALTER TABLE slaughters ADD CONSTRAINT slaughters_organisation_farm_fk FOREIGN KEY (organisation_id, farm_id) REFERENCES farms (organisation_id, id)');

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE slaughters ADD CONSTRAINT slaughters_birds_count_check CHECK (birds_count > 0)');
        }

        Schema::table('flocks', function (Blueprint $table) {
            $table->unsignedInteger('quantity_slaughtered')->default(0)->after('quantity_sold')
                ->comment('Sujets abattus (cumul des abattages)');
        });
    }

    public function down(): void
    {
        Schema::table('flocks', fn (Blueprint $table) => $table->dropColumn('quantity_slaughtered'));
        Schema::dropIfExists('slaughters');
    }
};
