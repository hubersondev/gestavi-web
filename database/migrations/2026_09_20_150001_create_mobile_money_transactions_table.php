<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Encaissements mobile money reçus par webhook (action 38), rapprochés
 * automatiquement ou manuellement avec une facture (payments).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_money_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farm_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete()
                ->comment('Compte mobile money marchand ayant reçu les fonds');
            $table->string('operator', 20);
            $table->string('external_id', 120)->comment('Identifiant de transaction chez l\'opérateur');
            $table->string('direction', 5)->default('in');
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('XOF');
            $table->string('payer_phone', 20)->nullable();
            $table->string('payer_name')->nullable();
            $table->string('reference')->nullable()->comment('Référence ou message saisi par le payeur (ex. numéro de facture)');
            $table->timestamp('occurred_at');
            $table->string('status', 10)->default('received');
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('matched_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('matched_at')->nullable();
            $table->jsonb('raw_payload')->nullable();
            $table->timestamps();

            $table->unique(['operator', 'external_id']);
            $table->index(['organisation_id', 'status']);
            $table->index(['organisation_id', 'occurred_at']);
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE mobile_money_transactions ADD CONSTRAINT mmt_operator_check CHECK (operator IN ('orange', 'mtn', 'moov', 'wave'))");
            DB::statement("ALTER TABLE mobile_money_transactions ADD CONSTRAINT mmt_direction_check CHECK (direction IN ('in', 'out'))");
            DB::statement("ALTER TABLE mobile_money_transactions ADD CONSTRAINT mmt_status_check CHECK (status IN ('received', 'matched', 'ignored'))");
            DB::statement('ALTER TABLE mobile_money_transactions ADD CONSTRAINT mmt_amount_positive_check CHECK (amount > 0)');
            DB::statement('ALTER TABLE mobile_money_transactions ADD CONSTRAINT mmt_organisation_farm_fk FOREIGN KEY (organisation_id, farm_id) REFERENCES farms (organisation_id, id)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_money_transactions');
    }
};
