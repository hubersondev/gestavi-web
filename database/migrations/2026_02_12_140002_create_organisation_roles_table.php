<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organisation_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('slug', 80);
            $table->tinyInteger('weight')->default(1);
            $table->boolean('is_system')->default(false);
            $table->string('description', 255)->nullable();
            $table->timestamps();

            $table->unique(['organisation_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organisation_roles');
    }
};
