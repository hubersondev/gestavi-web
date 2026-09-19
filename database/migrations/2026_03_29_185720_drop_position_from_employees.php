<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('employees', 'position')) {
            return;
        }

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('position');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('employees', 'position')) {
            return;
        }

        Schema::table('employees', function (Blueprint $table) {
            $table->string('position', 150)->nullable()->after('department');
        });
    }
};
