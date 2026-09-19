<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('employees', 'national_id_number')) {
            return;
        }

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('national_id_number');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('employees', 'national_id_number')) {
            return;
        }

        Schema::table('employees', function (Blueprint $table) {
            $table->string('national_id_number', 100)->nullable()->after('cnps_number');
        });
    }
};
