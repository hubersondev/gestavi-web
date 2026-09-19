<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Piste d'audit et protection de l'historique :
 * - les tables de faits ne sont plus supprimées en cascade avec leur parent
 *   (bande, employé, compte, stock, facture) : RESTRICT
 * - suppression logique des bandes et des bulletins de paie
 * - motif obligatoire pour la correction rétroactive d'un relevé quotidien
 * - colonnes d'authentification à deux facteurs sur les utilisateurs
 */
return new class extends Migration
{
    /** @var array<string, array{0: string, 1: string}> table => [colonne, table référencée] */
    private const RESTRICTED_FOREIGN_KEYS = [
        'daily_records' => ['flock_id', 'flocks'],
        'vaccinations' => ['flock_id', 'flocks'],
        'health_incidents' => ['flock_id', 'flocks'],
        'contracts' => ['employee_id', 'employees'],
        'payrolls' => ['employee_id', 'employees'],
        'attendances' => ['employee_id', 'employees'],
        'leaves' => ['employee_id', 'employees'],
        'transactions' => ['account_id', 'accounts'],
        'stock_movements' => ['stock_id', 'stocks'],
        'stock_lots' => ['stock_id', 'stocks'],
        'payments' => ['sale_id', 'sales'],
    ];

    public function up(): void
    {
        foreach (self::RESTRICTED_FOREIGN_KEYS as $table => [$column, $referenced]) {
            Schema::table($table, function (Blueprint $blueprint) use ($column, $referenced) {
                $blueprint->dropForeign([$column]);
                $blueprint->foreign($column)->references('id')->on($referenced)->restrictOnDelete();
            });
        }

        Schema::table('flocks', fn (Blueprint $table) => $table->softDeletes());
        Schema::table('payrolls', fn (Blueprint $table) => $table->softDeletes());

        Schema::table('daily_records', function (Blueprint $table) {
            $table->text('correction_reason')->nullable()->after('notes')
                ->comment('Motif obligatoire de toute correction rétroactive');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->text('app_authentication_secret')->nullable()->after('password');
            $table->text('app_authentication_recovery_codes')->nullable()->after('app_authentication_secret');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['app_authentication_secret', 'app_authentication_recovery_codes']));
        Schema::table('daily_records', fn (Blueprint $table) => $table->dropColumn('correction_reason'));
        Schema::table('payrolls', fn (Blueprint $table) => $table->dropSoftDeletes());
        Schema::table('flocks', fn (Blueprint $table) => $table->dropSoftDeletes());

        foreach (self::RESTRICTED_FOREIGN_KEYS as $table => [$column, $referenced]) {
            Schema::table($table, function (Blueprint $blueprint) use ($column, $referenced) {
                $blueprint->dropForeign([$column]);
                $blueprint->foreign($column)->references('id')->on($referenced)->cascadeOnDelete();
            });
        }
    }
};
