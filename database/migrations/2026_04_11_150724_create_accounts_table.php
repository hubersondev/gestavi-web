<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farm_id')->nullable()->constrained()->nullOnDelete();

            $table->string('name');
            $table->string('code', 50)->nullable();
            $table->string('type', 20);

            $table->string('bank_name')->nullable();
            $table->string('account_number', 50)->nullable();
            $table->string('phone_number', 20)->nullable();
            $table->string('operator', 20)->nullable();

            $table->decimal('initial_balance', 15, 2)->default(0);
            $table->decimal('current_balance', 15, 2)->default(0);
            $table->string('currency', 3)->default('XOF');

            $table->text('description')->nullable();
            $table->text('notes')->nullable();

            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organisation_id', 'code']);
            $table->index(['organisation_id', 'is_active']);
            $table->index(['farm_id', 'is_active']);
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE accounts ADD CONSTRAINT accounts_type_check CHECK (type IN ('cash','bank','mobile_money','other'))");
            DB::statement("ALTER TABLE accounts ADD CONSTRAINT accounts_operator_check CHECK (operator IS NULL OR operator IN ('orange','mtn','moov','wave'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
