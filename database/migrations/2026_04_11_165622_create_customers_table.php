<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farm_id')->nullable()->constrained()->nullOnDelete();

            $table->string('name');
            $table->string('code', 30)->nullable();
            $table->string('type', 20);

            $table->string('phone', 30)->nullable();
            $table->string('phone_secondary', 30)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();

            $table->decimal('credit_limit', 12, 2)->default(0);
            $table->decimal('balance', 12, 2)->default(0);

            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organisation_id', 'code']);
            $table->index(['organisation_id', 'is_active']);
            $table->index(['farm_id', 'is_active']);
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE customers ADD CONSTRAINT customers_type_check CHECK (type IN ('particulier','revendeur','restaurant','hotel','supermarche','autre'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
