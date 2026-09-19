<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('employees', 'employment_type')) {
            return;
        }

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('employment_type');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('employees', 'employment_type')) {
            return;
        }

        Schema::table('employees', function (Blueprint $table) {
            $table->string('employment_type', 20)->nullable()->after('department');
        });
    }
};
