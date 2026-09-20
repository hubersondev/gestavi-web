<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journal des saisies terrain synchronisées depuis l'application hors-ligne (action 37).
 * `client_uuid` garantit l'idempotence : une saisie renvoyée deux fois n'est traitée qu'une fois.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offline_sync_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('client_uuid')->unique();
            $table->string('type', 40)->comment('daily_record | vaccination_done');
            $table->jsonb('payload');
            $table->string('status', 20)->comment('created | duplicate | rejected');
            $table->text('message')->nullable();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->timestamp('recorded_at')->nullable()->comment('Horodatage de la saisie côté terrain');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['organisation_id', 'user_id', 'processed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offline_sync_entries');
    }
};
