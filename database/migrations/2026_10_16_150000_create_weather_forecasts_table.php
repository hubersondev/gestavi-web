<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Météo : prévisions journalières par ferme (agrégées depuis OpenWeatherMap) et sujet
 * « alertes météo » dans les préférences de notification.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weather_forecasts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farm_id')->constrained()->cascadeOnDelete();
            $table->date('forecast_date');
            $table->string('source', 30)->default('openweathermap');
            $table->decimal('temp_min', 5, 2)->nullable();
            $table->decimal('temp_max', 5, 2)->nullable();
            $table->decimal('temp_avg', 5, 2)->nullable();
            $table->decimal('humidity', 5, 2)->nullable();
            $table->decimal('wind_speed', 5, 2)->nullable()->comment('m/s');
            $table->decimal('wind_gust', 5, 2)->nullable();
            $table->decimal('rain_mm', 6, 2)->nullable();
            $table->decimal('clouds_percent', 5, 2)->nullable();
            $table->decimal('pressure_hpa', 7, 2)->nullable();
            $table->string('description', 100)->nullable();
            $table->string('icon', 10)->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();

            $table->unique(['farm_id', 'forecast_date', 'source']);
            $table->index(['organisation_id', 'forecast_date']);
        });

        Schema::table('notification_preferences', function (Blueprint $table): void {
            $table->boolean('email_weather')->default(true)->after('email_vaccinations');
            $table->boolean('app_weather')->default(true)->after('app_stock');
        });
    }

    public function down(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table): void {
            $table->dropColumn(['email_weather', 'app_weather']);
        });

        Schema::dropIfExists('weather_forecasts');
    }
};
