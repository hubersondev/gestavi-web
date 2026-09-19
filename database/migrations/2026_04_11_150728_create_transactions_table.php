<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('type', 10);
            $table->string('category', 20);

            $table->decimal('amount', 15, 2);
            $table->decimal('balance_before', 15, 2);
            $table->decimal('balance_after', 15, 2);

            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_number')->nullable();

            $table->string('description');
            $table->text('notes')->nullable();

            $table->date('transaction_date');
            $table->boolean('is_reconciled')->default(false);
            $table->timestamp('reconciled_at')->nullable();

            $table->timestamps();

            $table->index(['account_id', 'transaction_date']);
            $table->index(['reference_type', 'reference_id']);
            $table->index(['account_id', 'is_reconciled']);
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE transactions ADD CONSTRAINT transactions_type_check CHECK (type IN ('credit','debit'))");
            DB::statement("ALTER TABLE transactions ADD CONSTRAINT transactions_category_check CHECK (category IN ('opening','sale','payment','expense','transfer_in','transfer_out','adjustment','payroll','refund','other'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
