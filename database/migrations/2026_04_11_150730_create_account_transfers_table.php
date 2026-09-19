<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_transfers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('to_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('reference', 50)->nullable();
            $table->decimal('amount', 15, 2);
            $table->decimal('fees', 10, 2)->default(0);
            $table->date('transfer_date');
            $table->string('status', 20)->default('pending');

            $table->text('description')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['organisation_id', 'reference']);
            $table->index(['organisation_id', 'transfer_date']);
            $table->index(['organisation_id', 'status']);
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE account_transfers ADD CONSTRAINT account_transfers_status_check CHECK (status IN ('pending','completed','cancelled'))");
            DB::statement('ALTER TABLE account_transfers ADD CONSTRAINT account_transfers_different_accounts_check CHECK (from_account_id <> to_account_id)');
            DB::statement('ALTER TABLE account_transfers ADD CONSTRAINT account_transfers_amount_positive_check CHECK (amount > 0 AND fees >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('account_transfers');
    }
};
