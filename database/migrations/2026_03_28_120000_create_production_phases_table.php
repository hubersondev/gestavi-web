<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_phases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->nullable()->constrained('organisations')->nullOnDelete();
            $table->foreignId('chicken_type_id')->constrained('chicken_types')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('code', 50)->nullable();
            $table->integer('start_day');
            $table->integer('end_day');
            $table->decimal('min_temperature', 4, 1)->nullable();
            $table->decimal('max_temperature', 4, 1)->nullable();
            $table->decimal('humidity_min', 4, 1)->nullable();
            $table->decimal('humidity_max', 4, 1)->nullable();
            $table->decimal('light_hours', 4, 1)->nullable();
            $table->decimal('density_per_m2', 5, 2)->nullable();
            $table->text('feed_type')->nullable();
            $table->text('description')->nullable();
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organisation_id', 'chicken_type_id', 'code']);
            $table->index(['organisation_id', 'chicken_type_id', 'start_day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_phases');
    }
};
