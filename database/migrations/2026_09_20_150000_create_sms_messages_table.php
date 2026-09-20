<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Journal des SMS émis (action 38) et opt-in SMS des utilisateurs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('to', 20);
            $table->string('message', 480);
            $table->string('driver', 30);
            $table->string('status', 10)->default('queued');
            $table->string('provider_id')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['organisation_id', 'created_at']);
            $table->index('status');
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE sms_messages ADD CONSTRAINT sms_messages_status_check CHECK (status IN ('queued', 'sent', 'failed'))");
        }

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('sms_alerts_enabled')->default(false)->after('phone')
                ->comment('Opt-in aux alertes par SMS (nécessite un numéro valide)');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('sms_alerts_enabled'));
        Schema::dropIfExists('sms_messages');
    }
};
