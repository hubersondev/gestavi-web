<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organisation_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20)->default('employee');
            $table->string('status', 20)->default('active');
            $table->jsonb('permissions')->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['organisation_id', 'user_id']);
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE organisation_users ADD CONSTRAINT organisation_users_role_check CHECK (role IN ('owner', 'admin', 'manager', 'employee'))");
            DB::statement("ALTER TABLE organisation_users ADD CONSTRAINT organisation_users_status_check CHECK (status IN ('active', 'invited', 'suspended'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('organisation_users');
    }
};
