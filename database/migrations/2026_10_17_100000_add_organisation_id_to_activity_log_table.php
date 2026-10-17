<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Journal d'audit consultable par organisation : `activity_log.organisation_id`, posé à l'écriture
 * par le trait LogsAuditTrail et repris ici pour l'historique à partir des enregistrements audités.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = config('activitylog.table_name', 'activity_log');

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->unsignedBigInteger('organisation_id')->nullable()->after('log_name')->index();
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // Reprise : chaque type de sujet audité porte organisation_id ; les sujets supprimés depuis
        // sont repris depuis les attributs journalisés
        $types = DB::table($table)->whereNull('organisation_id')->distinct()->pluck('subject_type');

        foreach ($types as $class) {
            if (! is_string($class) || ! class_exists($class) || ! is_subclass_of($class, Model::class)) {
                continue;
            }

            $subjectTable = (new $class)->getTable();

            if (! Schema::hasColumn($subjectTable, 'organisation_id')) {
                continue;
            }

            DB::statement(<<<SQL
                UPDATE {$table} a
                SET organisation_id = s.organisation_id
                FROM {$subjectTable} s
                WHERE a.subject_type = ? AND a.subject_id = s.id AND a.organisation_id IS NULL
            SQL, [$class]);
        }

        DB::statement(<<<SQL
            UPDATE {$table}
            SET organisation_id = NULLIF(COALESCE(properties->'attributes'->>'organisation_id', properties->'old'->>'organisation_id'), '')::bigint
            WHERE organisation_id IS NULL
              AND COALESCE(properties->'attributes'->>'organisation_id', properties->'old'->>'organisation_id') ~ '^[0-9]+$'
        SQL);
    }

    public function down(): void
    {
        Schema::table(config('activitylog.table_name', 'activity_log'), function (Blueprint $blueprint): void {
            $blueprint->dropColumn('organisation_id');
        });
    }
};
