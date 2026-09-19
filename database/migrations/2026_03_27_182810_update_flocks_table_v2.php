<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flocks', function (Blueprint $table) {
            // Remplace supplier_name (texte libre) par une FK vers le modèle Supplier
            $table->foreignId('supplier_id')
                ->nullable()
                ->after('chicken_type_id')
                ->constrained('suppliers')
                ->nullOnDelete();

            // Supprime is_active : redondant avec status (Planned/Active/Completed/Cancelled)
            $table->dropColumn(['supplier_name', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('flocks', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropColumn('supplier_id');

            $table->string('supplier_name')->nullable()->after('status');
            $table->boolean('is_active')->default(true)->after('notes');
        });
    }
};
