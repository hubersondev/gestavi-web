<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->foreignId('city_id')->nullable()->after('address')->constrained()->nullOnDelete();
            $table->foreignId('country_id')->nullable()->after('city_id')->constrained()->nullOnDelete();

            $table->dropColumn(['city', 'country']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('city_id');
            $table->dropConstrainedForeignId('country_id');

            $table->string('city')->nullable()->after('address');
            $table->string('country')->nullable()->after('city');
        });
    }
};
