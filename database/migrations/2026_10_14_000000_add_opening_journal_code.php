<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Journal des à-nouveaux (AN) : écriture d'ouverture générée à la clôture d'un exercice,
 * qui reporte les soldes des comptes de bilan sur le premier jour de l'exercice suivant.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE journal_entries DROP CONSTRAINT IF EXISTS journal_entries_journal_check');
        DB::statement("ALTER TABLE journal_entries ADD CONSTRAINT journal_entries_journal_check CHECK (journal IN ('AC','VE','BQ','CA','OD','PA','AN'))");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE journal_entries DROP CONSTRAINT IF EXISTS journal_entries_journal_check');
        DB::statement("ALTER TABLE journal_entries ADD CONSTRAINT journal_entries_journal_check CHECK (journal IN ('AC','VE','BQ','CA','OD','PA'))");
    }
};
