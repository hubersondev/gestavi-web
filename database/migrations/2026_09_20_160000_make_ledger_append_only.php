<?php

use App\Services\LedgerService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ledger comptable intangible :
 * - chaîne de hachage sur les écritures (previous_hash → hash) ;
 * - table d'ancrage des têtes de chaîne (clôture, reconstruction, planifié) ;
 * - clés étrangères en RESTRICT (une écriture ne disparaît jamais par cascade) ;
 * - triggers PostgreSQL interdisant UPDATE/DELETE sur les journaux, sauf colonnes
 *   de rapprochement et déverrouillage explicite (SET LOCAL gestavi.ledger_unlock = 'on').
 */
return new class extends Migration
{
    private const array GUARDED_TABLES = [
        'journal_entries' => ['user_id', 'updated_at'],
        'journal_lines' => ['updated_at'],
        'transactions' => ['is_reconciled', 'reconciled_at', 'user_id', 'updated_at'],
        'ledger_anchors' => [],
    ];

    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->string('previous_hash', 64)->nullable()->after('is_generated')->comment('Hash de l\'écriture précédente de l\'organisation (chaîne)');
            $table->string('hash', 64)->nullable()->after('previous_hash')->comment('SHA-256 du contenu canonique + previous_hash');

            $table->unique(['organisation_id', 'hash']);
        });

        Schema::create('ledger_anchors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('head_entry_id')->nullable()->comment('Sans clé étrangère : l\'ancrage survit à une reconstruction du journal');
            $table->string('head_hash', 64)->nullable();
            $table->unsignedInteger('entry_count')->default(0);
            $table->string('reason', 30);
            $table->boolean('is_valid')->default(true)->comment('Chaîne vérifiée intègre au moment de l\'ancrage');
            $table->foreignId('anchored_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['organisation_id', 'created_at']);
        });

        // Les hachages des écritures existantes sont calculés avant la pose des verrous
        foreach (DB::table('journal_entries')->distinct()->pluck('organisation_id') as $organisationId) {
            app(LedgerService::class)->rehash((int) $organisationId);
        }

        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE ledger_anchors ADD CONSTRAINT ledger_anchors_reason_check CHECK (reason IN ('scheduled','fiscal_year_close','rebuild','manual'))");

        // Clés étrangères : plus aucune cascade ni mise à NULL ne peut altérer un journal
        $this->replaceForeignKey('journal_lines', 'journal_entry_id', 'journal_entries', 'RESTRICT');
        $this->replaceForeignKey('journal_lines', 'organisation_id', 'organisations', 'RESTRICT');
        $this->replaceForeignKey('journal_lines', 'farm_id', 'farms', 'RESTRICT');
        $this->replaceForeignKey('journal_entries', 'organisation_id', 'organisations', 'RESTRICT');
        $this->replaceForeignKey('journal_entries', 'farm_id', 'farms', 'RESTRICT');
        $this->replaceForeignKey('journal_entries', 'fiscal_year_id', 'fiscal_years', 'RESTRICT');
        $this->replaceForeignKey('journal_entries', 'reversed_entry_id', 'journal_entries', 'RESTRICT');

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION gestavi_ledger_guard() RETURNS trigger AS $$
            DECLARE
                allowed text[] := COALESCE(TG_ARGV, '{}'::text[]);
                changed text;
            BEGIN
                IF current_setting('gestavi.ledger_unlock', true) = 'on' THEN
                    IF TG_OP = 'DELETE' THEN RETURN OLD; END IF;
                    RETURN NEW;
                END IF;

                IF TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION 'Journal intangible : suppression interdite sur % (id %). Passez par une extourne.', TG_TABLE_NAME, OLD.id
                        USING ERRCODE = 'GV001';
                END IF;

                SELECT string_agg(n.key, ', ' ORDER BY n.key)
                INTO changed
                FROM jsonb_each(to_jsonb(NEW)) AS n
                WHERE to_jsonb(OLD) -> n.key IS DISTINCT FROM n.value
                  AND NOT (n.key = ANY (allowed));

                IF changed IS NOT NULL THEN
                    RAISE EXCEPTION 'Journal intangible : modification interdite sur % (id %, colonnes : %). Passez par une extourne.', TG_TABLE_NAME, OLD.id, changed
                        USING ERRCODE = 'GV001';
                END IF;

                RETURN NEW;
            END
            $$ LANGUAGE plpgsql;
        SQL);

        foreach (self::GUARDED_TABLES as $table => $allowedColumns) {
            $arguments = implode(', ', array_map(fn (string $column): string => "'{$column}'", $allowedColumns));

            DB::unprepared("CREATE TRIGGER {$table}_ledger_guard BEFORE UPDATE OR DELETE ON {$table} FOR EACH ROW EXECUTE FUNCTION gestavi_ledger_guard({$arguments})");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            foreach (array_keys(self::GUARDED_TABLES) as $table) {
                DB::unprepared("DROP TRIGGER IF EXISTS {$table}_ledger_guard ON {$table}");
            }

            DB::unprepared('DROP FUNCTION IF EXISTS gestavi_ledger_guard()');

            $this->replaceForeignKey('journal_lines', 'journal_entry_id', 'journal_entries', 'CASCADE');
            $this->replaceForeignKey('journal_lines', 'organisation_id', 'organisations', 'CASCADE');
            $this->replaceForeignKey('journal_lines', 'farm_id', 'farms', 'SET NULL');
            $this->replaceForeignKey('journal_entries', 'organisation_id', 'organisations', 'CASCADE');
            $this->replaceForeignKey('journal_entries', 'farm_id', 'farms', 'SET NULL');
            $this->replaceForeignKey('journal_entries', 'fiscal_year_id', 'fiscal_years', 'SET NULL');
            $this->replaceForeignKey('journal_entries', 'reversed_entry_id', 'journal_entries', 'SET NULL');
        }

        Schema::dropIfExists('ledger_anchors');

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropUnique(['organisation_id', 'hash']);
            $table->dropColumn(['previous_hash', 'hash']);
        });
    }

    private function replaceForeignKey(string $table, string $column, string $references, string $onDelete): void
    {
        DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$table}_{$column}_foreign");
        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_{$column}_foreign FOREIGN KEY ({$column}) REFERENCES {$references}(id) ON DELETE {$onDelete}");
    }
};
