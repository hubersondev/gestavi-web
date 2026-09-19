<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajout du soft delete sur organisations et farms.
 * La suppression accidentelle d'une organisation ou d'une ferme ne doit pas
 * être irréversible — cascade hard-delete sur toutes les données en production.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('farms', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('farms', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
