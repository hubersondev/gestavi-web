<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_categories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('slug');
            $table->string('type', 20);
            $table->text('description')->nullable();

            $table->timestamps();

            $table->unique(['organisation_id', 'slug']);
            $table->index('type');
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE stock_categories ADD CONSTRAINT stock_categories_type_check CHECK (type IN ('feed','medicine','material','packaging','other'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_categories');
    }
};
