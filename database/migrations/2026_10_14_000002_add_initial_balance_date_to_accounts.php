<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Date du solde initial d'un compte de trésorerie : la transaction d'ouverture est datée de ce jour
 * (par exemple le 1er janvier de l'exercice repris) et non du jour de création de la fiche.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->date('initial_balance_date')->nullable()->after('initial_balance')
                ->comment("Date de la transaction d'ouverture portant le solde initial");
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('initial_balance_date');
        });
    }
};
