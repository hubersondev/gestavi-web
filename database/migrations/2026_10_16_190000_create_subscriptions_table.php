<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Abonnements des organisations à un plan : période, cycle de facturation, essai, paiement,
 * annulation ; historique conservé (un abonnement courant au plus par organisation).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('plan_name', 100)->comment('Nom du plan au moment de la souscription (historique)');
            $table->string('status', 20)->default('active');
            $table->string('billing_cycle', 20)->default('monthly');
            $table->decimal('price', 12, 2)->default(0)->comment('Prix de la période en cours');
            $table->string('currency', 3)->default('XOF');
            $table->date('starts_at');
            $table->date('ends_at');
            $table->date('trial_ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->string('payment_method', 30)->nullable();
            $table->string('payment_reference')->nullable();
            $table->text('notes')->nullable();
            $table->json('alerted_thresholds')->nullable()->comment('Seuils J-x déjà notifiés pour l\'échéance en cours');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organisation_id', 'status']);
            $table->index('ends_at');
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE subscriptions ADD CONSTRAINT subscriptions_status_check CHECK (status IN ('trial', 'active', 'cancelled', 'expired'))");
            DB::statement("ALTER TABLE subscriptions ADD CONSTRAINT subscriptions_billing_cycle_check CHECK (billing_cycle IN ('monthly', 'quarterly', 'yearly'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
