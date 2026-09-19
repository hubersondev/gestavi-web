<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique()->comment('Nom complet de la devise');
            $table->string('code', 3)->unique()->comment('Code ISO 4217 (XOF, EUR, USD...)');
            $table->string('symbol', 10)->comment('Symbole affiché (FCFA, €, $...)');
            $table->tinyInteger('decimal_places')->default(2)->comment('Nombre de décimales');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
