<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('payment_method', 20)->nullable()->after('cnps_number');
        });

        DB::statement("ALTER TABLE employees ADD CONSTRAINT employees_payment_method_check CHECK (payment_method IN ('cash','bank_transfer','mobile_money'))");
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('payment_method');
        });
    }
};
