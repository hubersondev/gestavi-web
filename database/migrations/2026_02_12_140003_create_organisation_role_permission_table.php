<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organisation_role_permission', function (Blueprint $table) {
            $table->foreignId('organisation_role_id')->constrained('organisation_roles')->cascadeOnDelete();
            $table->foreignId('organisation_permission_id')->constrained('organisation_permissions')->cascadeOnDelete();

            $table->primary(['organisation_role_id', 'organisation_permission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organisation_role_permission');
    }
};
