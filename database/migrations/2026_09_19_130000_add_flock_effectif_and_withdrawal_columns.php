<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Effectif dérivé et délai d'attente :
 * - daily_records.culls (réformes/tri) et birds_present (effectif présent ce jour, dénormalisé pour les taux)
 * - flocks.quantity_sold (sujets vivants vendus, cumul)
 * - sale_items.is_live_birds (la ligne décrémente l'effectif de la bande)
 * - vaccinations.withdrawal_days / withdrawal_until (délai d'attente avant vente/abattage)
 * - chicken_types.production_kind (broiler | layer | breeder | mixed) pour remplacer la détection par libellé
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_records', function (Blueprint $table) {
            $table->unsignedInteger('culls')->default(0)->after('mortality_cause')
                ->comment('Sujets réformés / triés (sortis vivants du lot, hors mortalité)');
            $table->unsignedInteger('birds_present')->nullable()->after('culls')
                ->comment('Effectif présent en fin de journée, base des taux journaliers');
        });

        Schema::table('flocks', function (Blueprint $table) {
            $table->unsignedInteger('quantity_sold')->default(0)->after('quantity_current')
                ->comment('Sujets vivants vendus (cumul des lignes de vente is_live_birds)');
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->boolean('is_live_birds')->default(false)->after('flock_id')
                ->comment('Vente de sujets vivants : décrémente l\'effectif de la bande liée');
        });

        Schema::table('vaccinations', function (Blueprint $table) {
            $table->unsignedSmallInteger('withdrawal_days')->default(0)->after('dosage');
            $table->date('withdrawal_until')->nullable()->after('withdrawal_days')
                ->comment('Aucune vente ni abattage avant cette date');
            $table->index('withdrawal_until');
        });

        Schema::table('chicken_types', function (Blueprint $table) {
            $table->string('production_kind', 20)->default('mixed')->after('name');
        });

        DB::table('chicken_types')->where('name', 'ilike', 'ponde%')->update(['production_kind' => 'layer']);
        DB::table('chicken_types')->where('name', 'ilike', 'chair%')->update(['production_kind' => 'broiler']);
        DB::table('chicken_types')->where('name', 'ilike', 'reproduct%')->update(['production_kind' => 'breeder']);

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE chicken_types ADD CONSTRAINT chicken_types_production_kind_check CHECK (production_kind IN ('broiler', 'layer', 'breeder', 'mixed'))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE chicken_types DROP CONSTRAINT IF EXISTS chicken_types_production_kind_check');
        }

        Schema::table('chicken_types', fn (Blueprint $table) => $table->dropColumn('production_kind'));
        Schema::table('vaccinations', function (Blueprint $table) {
            $table->dropIndex(['withdrawal_until']);
            $table->dropColumn(['withdrawal_days', 'withdrawal_until']);
        });
        Schema::table('sale_items', fn (Blueprint $table) => $table->dropColumn('is_live_birds'));
        Schema::table('flocks', fn (Blueprint $table) => $table->dropColumn('quantity_sold'));
        Schema::table('daily_records', fn (Blueprint $table) => $table->dropColumn(['culls', 'birds_present']));
    }
};
