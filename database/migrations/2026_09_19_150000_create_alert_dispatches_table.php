<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journal des alertes planifiées envoyées : une alerte (clé fonctionnelle)
 * n'est émise qu'une fois par organisation et par jour, même si la commande
 * est relancée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_dispatches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->string('alert_key', 60)->comment('overdue_vaccinations, missing_daily_records, overdue_invoices, low_stock…');
            $table->date('dispatched_on');
            $table->unsignedInteger('items_count')->default(0);
            $table->unsignedInteger('recipients_count')->default(0);
            $table->timestamps();

            $table->unique(['organisation_id', 'alert_key', 'dispatched_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_dispatches');
    }
};
