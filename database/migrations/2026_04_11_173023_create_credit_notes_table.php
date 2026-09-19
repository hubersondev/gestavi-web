<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_notes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('credit_note_number', 50);
            $table->date('issue_date');
            $table->decimal('amount', 12, 2);
            $table->string('reason', 255)->nullable();
            $table->text('description')->nullable();

            $table->string('status', 20)->default('draft');
            $table->timestamp('applied_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organisation_id', 'credit_note_number']);
            $table->index(['organisation_id', 'status']);
            $table->index(['sale_id', 'status']);
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE credit_notes ADD CONSTRAINT credit_notes_status_check CHECK (status IN ('draft','issued','applied','cancelled'))");
            DB::statement('ALTER TABLE credit_notes ADD CONSTRAINT credit_notes_amount_positive_check CHECK (amount > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_notes');
    }
};
