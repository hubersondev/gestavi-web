<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rendu idempotent : la migration de création utilise déjà chicken_type_id,
        // donc cette bascule n'est utile que si on part d'une base historique avec bird_type.
        if (! Schema::hasColumn('health_programs', 'bird_type')) {
            return;
        }

        Schema::table('health_programs', function (Blueprint $table) {
            $table->dropIndex('hp_org_bird_day_index');
        });

        Schema::table('health_programs', function (Blueprint $table) {
            $table->dropColumn('bird_type');

            $table->foreignId('chicken_type_id')
                ->nullable()
                ->after('farm_id')
                ->constrained()
                ->nullOnDelete();
        });

        Schema::table('health_programs', function (Blueprint $table) {
            $table->index(
                ['organisation_id', 'chicken_type_id', 'scheduled_day'],
                'hp_org_bird_day_index',
            );
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('health_programs', 'chicken_type_id')) {
            return;
        }

        Schema::table('health_programs', function (Blueprint $table) {
            $table->dropIndex('hp_org_bird_day_index');
        });

        Schema::table('health_programs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('chicken_type_id');

            $table->string('bird_type', 30)->default('tous')->after('farm_id');
        });

        Schema::table('health_programs', function (Blueprint $table) {
            $table->index(
                ['organisation_id', 'bird_type', 'scheduled_day'],
                'hp_org_bird_day_index',
            );
        });
    }
};
