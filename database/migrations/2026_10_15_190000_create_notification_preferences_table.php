<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Préférences de notification par utilisateur : quels sujets arrivent par e-mail, dans la cloche
 * du panel et par SMS. Sans ligne, les valeurs par défaut s'appliquent (tout sauf les paiements
 * dans l'application et les SMS). L'activation des SMS reste `users.sms_alerts_enabled`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            // E-mail
            $table->boolean('email_daily_report')->default(true);
            $table->boolean('email_alerts')->default(true);
            $table->boolean('email_stock')->default(true);
            $table->boolean('email_vaccinations')->default(true);
            $table->boolean('email_payments')->default(true);

            // Dans l'application (cloche du panel, onglet Alertes du mobile)
            $table->boolean('app_enabled')->default(true);
            $table->boolean('app_alerts')->default(true);
            $table->boolean('app_stock')->default(true);
            $table->boolean('app_payments')->default(false);

            // SMS (activation : users.sms_alerts_enabled)
            $table->boolean('sms_critical_alerts')->default(true);
            $table->boolean('sms_payments')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
