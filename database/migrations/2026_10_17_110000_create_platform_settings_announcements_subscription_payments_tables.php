<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Administration de la plateforme : réglages modifiables en interface, annonces aux
 * organisations, encaissements d'abonnement (une ligne par paiement, numérotée pour la facture).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table): void {
            $table->string('key', 60)->primary();
            $table->json('value')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('announcements', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 150);
            $table->text('body');
            $table->string('level', 20)->default('info');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete()->comment('Cible : organisations de ce plan');
            $table->foreignId('organisation_id')->nullable()->constrained()->cascadeOnDelete()->comment('Cible : cette organisation seulement');
            $table->boolean('is_active')->default(true);
            $table->timestamp('notified_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'starts_at', 'ends_at']);
        });

        Schema::create('subscription_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->string('invoice_number', 30)->unique();
            $table->string('plan_name', 100);
            $table->string('billing_cycle', 20);
            $table->date('period_from');
            $table->date('period_to');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('XOF');
            $table->date('paid_at');
            $table->string('payment_method', 30)->nullable();
            $table->string('payment_reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organisation_id', 'paid_at']);
            $table->index('paid_at');
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE announcements ADD CONSTRAINT announcements_level_check CHECK (level IN ('info', 'warning', 'danger'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('platform_settings');
    }
};
