<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farm_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('farm_id')->constrained('farms')->cascadeOnDelete();
            $table->foreignId('flock_id')->nullable()->constrained('flocks')->nullOnDelete();
            $table->foreignId('building_id')->nullable()->constrained('buildings')->nullOnDelete();
            $table->foreignId('recorded_by')->constrained('users');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();

            $table->date('log_date');
            $table->time('log_time')->nullable();

            $table->string('title');
            $table->string('category')->default('observation');
            $table->string('priority')->default('normal');

            $table->text('description')->nullable();
            $table->text('actions_taken')->nullable();
            $table->json('attachments')->nullable();

            $table->string('weather')->nullable();
            $table->decimal('temperature', 4, 1)->nullable();
            $table->decimal('humidity', 4, 1)->nullable();

            $table->boolean('requires_follow_up')->default(false);
            $table->date('follow_up_date')->nullable();

            $table->boolean('is_resolved')->default(false);
            $table->timestamp('resolved_at')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['farm_id', 'log_date']);
            $table->index(['farm_id', 'category']);
            $table->index(['flock_id', 'log_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farm_logs');
    }
};
