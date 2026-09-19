<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un avoir imputé se décompose en réduction de créance (applied_amount, sans
 * mouvement de trésorerie) et remboursement réel (refunded_amount, débit d'un compte).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_notes', function (Blueprint $table) {
            $table->decimal('applied_amount', 12, 2)->default(0)->after('amount')
                ->comment('Part imputée en réduction du solde dû de la facture');
            $table->decimal('refunded_amount', 12, 2)->default(0)->after('applied_amount')
                ->comment('Part remboursée au client (débit de trésorerie)');
            $table->foreignId('refund_account_id')->nullable()->after('refunded_amount')
                ->constrained('accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('credit_notes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('refund_account_id');
            $table->dropColumn(['applied_amount', 'refunded_amount']);
        });
    }
};
