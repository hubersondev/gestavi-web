<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rapprochement bancaire par relevé :
 * - bank_reconciliations : un relevé (compte, date, solde) en cours ou clôturé ;
 * - bank_statement_lines : lignes importées du relevé, associées ou non à une transaction ;
 * - transactions.bank_reconciliation_id : rapprochement dans lequel la transaction a été pointée.
 * Le déclencheur d'intangibilité du journal autorise cette nouvelle colonne avec celles du pointage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_reconciliations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farm_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->date('statement_date');
            $table->decimal('statement_balance', 15, 2);
            $table->string('status', 20)->default('draft');
            $table->string('statement_file')->nullable()->comment('Nom du fichier de relevé importé');
            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['account_id', 'statement_date']);
            $table->index(['organisation_id', 'status']);
        });

        Schema::create('bank_statement_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bank_reconciliation_id')->constrained()->cascadeOnDelete();
            $table->date('line_date');
            $table->string('label');
            $table->decimal('amount', 15, 2)->comment('Signé : positif = entrée sur le relevé, négatif = sortie');
            $table->string('reference', 100)->nullable();
            $table->string('status', 20)->default('unmatched');
            $table->foreignId('matched_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->timestamps();

            $table->index(['bank_reconciliation_id', 'status']);
        });

        Schema::table('transactions', function (Blueprint $table): void {
            $table->foreignId('bank_reconciliation_id')->nullable()->after('reconciled_at')->constrained()->nullOnDelete();
        });

        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE bank_reconciliations ADD CONSTRAINT bank_reconciliations_status_check CHECK (status IN ('draft', 'completed'))");
        DB::statement("ALTER TABLE bank_statement_lines ADD CONSTRAINT bank_statement_lines_status_check CHECK (status IN ('unmatched', 'matched', 'ignored'))");

        DB::unprepared('DROP TRIGGER IF EXISTS transactions_ledger_guard ON transactions');
        DB::unprepared("CREATE TRIGGER transactions_ledger_guard BEFORE UPDATE OR DELETE ON transactions FOR EACH ROW EXECUTE FUNCTION gestavi_ledger_guard('is_reconciled', 'reconciled_at', 'bank_reconciliation_id', 'user_id', 'updated_at')");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS transactions_ledger_guard ON transactions');
            DB::unprepared("CREATE TRIGGER transactions_ledger_guard BEFORE UPDATE OR DELETE ON transactions FOR EACH ROW EXECUTE FUNCTION gestavi_ledger_guard('is_reconciled', 'reconciled_at', 'user_id', 'updated_at')");
        }

        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('bank_reconciliation_id');
        });

        Schema::dropIfExists('bank_statement_lines');
        Schema::dropIfExists('bank_reconciliations');
    }
};
