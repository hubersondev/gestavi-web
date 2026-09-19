<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * organisation_role_id était nullable pour permettre la migration de données initiale.
 * Tous les membres doivent avoir un rôle — on rend la colonne NOT NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Assigner le rôle "employee" aux membres sans rôle (données orphelines éventuelles)
        $employeeRoles = DB::table('organisation_roles')
            ->where('slug', 'employee')
            ->get()
            ->keyBy('organisation_id');

        DB::table('organisation_users')
            ->whereNull('organisation_role_id')
            ->get()
            ->each(function ($member) use ($employeeRoles) {
                $role = $employeeRoles[$member->organisation_id] ?? null;

                if ($role) {
                    DB::table('organisation_users')
                        ->where('id', $member->id)
                        ->update(['organisation_role_id' => $role->id]);
                } else {
                    // Supprimer les membres dont l'organisation n'a plus de rôles (cas impossible en production)
                    DB::table('organisation_users')->where('id', $member->id)->delete();
                }
            });

        Schema::table('organisation_users', function (Blueprint $table) {
            $table->foreignId('organisation_role_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('organisation_users', function (Blueprint $table) {
            $table->foreignId('organisation_role_id')->nullable()->change();
        });
    }
};
