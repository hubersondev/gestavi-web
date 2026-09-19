<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('platform_role', 20)->nullable()->after('name');
            $table->boolean('is_active')->default(true)->after('platform_role');
            $table->string('phone', 20)->nullable()->after('email');
            $table->string('avatar_url')->nullable()->after('phone');
            $table->softDeletes();
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_platform_role_check CHECK (platform_role IN ('super_admin', 'admin', 'user'))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_platform_role_check');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn(['platform_role', 'is_active', 'phone', 'avatar_url']);
        });
    }
};
