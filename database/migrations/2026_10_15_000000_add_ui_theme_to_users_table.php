<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Apparence choisie par chaque utilisateur : thème (Sous-bois le jour, Nuit en sombre) et couleur
 * principale parmi les présélections de la charte. Mémorisées en base, donc valables sur tout appareil.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('ui_theme', 20)->nullable()->after('sms_alerts_enabled')->comment('sous-bois ou nuit ; vide = sous-bois');
            $table->string('ui_primary_color', 20)->nullable()->after('ui_theme')->comment('Clé de couleur principale de la charte ; vide = vert GESTAVI');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['ui_theme', 'ui_primary_color']);
        });
    }
};
