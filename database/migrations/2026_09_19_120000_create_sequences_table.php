<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Compteurs de numérotation verrouillables (factures, devis, dépenses, codes…).
 * Remplace les générateurs basés sur count()+1, sujets aux collisions en
 * concurrence et aux régressions après suppression.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sequences', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 30)->comment('organisation | farm | employee | contract');
            $table->unsignedBigInteger('scope_id');
            $table->string('key', 40)->comment('invoice, quote, expense, customer…');
            $table->unsignedSmallInteger('year')->nullable()->comment('NULL pour les compteurs sans remise à zéro annuelle');
            $table->unsignedInteger('last_value')->default(0);
            $table->timestamps();

            $table->unique(['scope', 'scope_id', 'key', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sequences');
    }
};
