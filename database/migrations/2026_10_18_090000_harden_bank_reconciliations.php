<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rapprochement bancaire, suite de la revue :
 * - bank_reconciliations.reconciled_balance : solde pointé figé à la clôture (un rapprochement
 *   clôturé n'est plus recalculé quand des mouvements sont pointés ensuite) ;
 * - index partiel sur transactions.bank_reconciliation_id (listes, comptages, ON DELETE SET NULL) ;
 * - un seul rapprochement en cours par compte, garanti par un index unique partiel ;
 * - plan Pro existant : fonctionnalités ajoutées au seeder depuis sa création (le seeder ne
 *   modifie pas un plan déjà en base).
 */
return new class extends Migration
{
    /** Fonctionnalités ajoutées au plan Pro après la création des bases existantes. */
    private const array PRO_FEATURES_ADDED = ['ai_predictions', 'weather_integration', 'multi_farm_comparison', 'bank_reconciliation'];

    public function up(): void
    {
        Schema::table('bank_reconciliations', function (Blueprint $table): void {
            $table->decimal('reconciled_balance', 15, 2)->nullable()->after('statement_balance')->comment('Solde pointé figé à la clôture');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX IF NOT EXISTS transactions_bank_reconciliation_id_index ON transactions (bank_reconciliation_id) WHERE bank_reconciliation_id IS NOT NULL');
            DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS bank_reconciliations_one_draft_per_account ON bank_reconciliations (account_id) WHERE status = 'draft'");
        } else {
            Schema::table('transactions', function (Blueprint $table): void {
                $table->index('bank_reconciliation_id');
            });
        }

        $this->addProFeatures();
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS bank_reconciliations_one_draft_per_account');
            DB::statement('DROP INDEX IF EXISTS transactions_bank_reconciliation_id_index');
        } else {
            Schema::table('transactions', function (Blueprint $table): void {
                $table->dropIndex(['bank_reconciliation_id']);
            });
        }

        Schema::table('bank_reconciliations', function (Blueprint $table): void {
            $table->dropColumn('reconciled_balance');
        });
    }

    private function addProFeatures(): void
    {
        $plan = DB::table('plans')->where('slug', 'pro')->first(['id', 'features']);

        if ($plan === null) {
            return;
        }

        $features = json_decode((string) $plan->features, true);
        $features = is_array($features) ? $features : [];
        $merged = array_values(array_unique(array_merge($features, self::PRO_FEATURES_ADDED)));

        if (count($merged) !== count($features)) {
            DB::table('plans')->where('id', $plan->id)->update(['features' => json_encode($merged)]);
        }
    }
};
