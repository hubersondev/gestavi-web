<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Le rattachement d'une écriture à un exercice (fiscal_year_id) est une classification, absente du
 * hachage du ledger : le garde-fou l'autorise désormais, pour rattacher rétroactivement les écritures
 * d'une période à un exercice créé ou redaté après coup. Le contenu comptable reste intangible.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->recreateGuard(['fiscal_year_id', 'user_id', 'updated_at']);
    }

    public function down(): void
    {
        $this->recreateGuard(['user_id', 'updated_at']);
    }

    /**
     * @param  list<string>  $allowedColumns
     */
    private function recreateGuard(array $allowedColumns): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        $arguments = implode(', ', array_map(fn (string $column): string => "'{$column}'", $allowedColumns));

        DB::unprepared('DROP TRIGGER IF EXISTS journal_entries_ledger_guard ON journal_entries');
        DB::unprepared("CREATE TRIGGER journal_entries_ledger_guard BEFORE UPDATE OR DELETE ON journal_entries FOR EACH ROW EXECUTE FUNCTION gestavi_ledger_guard({$arguments})");
    }
};
