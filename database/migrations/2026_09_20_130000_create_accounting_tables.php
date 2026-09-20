<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Comptabilité SYSCOHADA minimale (action 35) : plan comptable, paramétrage
 * des imputations, exercices, journal en partie double, TVA sur les dépenses.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chart_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->nullable()->constrained()->cascadeOnDelete()
                ->comment('NULL = plan de référence SYSCOHADA révisé');
            $table->string('code', 20);
            $table->string('label');
            $table->unsignedTinyInteger('class');
            $table->string('type', 20);
            $table->boolean('is_active')->default(true);
            $table->string('source')->nullable();
            $table->timestamps();

            $table->unique(['organisation_id', 'code']);
            $table->index(['organisation_id', 'class']);
        });

        Schema::create('accounting_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->nullable()->constrained()->cascadeOnDelete()
                ->comment('NULL = paramétrage par défaut');
            $table->string('mapping_key', 80);
            $table->string('chart_account_code', 20);
            $table->string('description')->nullable();
            $table->timestamps();

            $table->unique(['organisation_id', 'mapping_key']);
        });

        Schema::create('fiscal_years', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->string('label', 50);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 10)->default('open');
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('net_result', 15, 2)->nullable()->comment('Résultat net constaté à la clôture');
            $table->timestamps();

            $table->unique(['organisation_id', 'label']);
            $table->index(['organisation_id', 'starts_on', 'ends_on']);
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farm_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('fiscal_year_id')->nullable()->constrained()->nullOnDelete();
            $table->string('entry_number', 30);
            $table->date('entry_date');
            $table->string('journal', 2);
            $table->string('label');
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_number')->nullable();
            $table->boolean('is_reversal')->default(false);
            $table->foreignId('reversed_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->boolean('is_generated')->default(true)->comment('Générée automatiquement depuis un événement métier');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organisation_id', 'entry_number']);
            $table->index(['organisation_id', 'entry_date']);
            $table->index(['organisation_id', 'journal']);
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farm_id')->nullable()->constrained()->nullOnDelete();
            $table->date('entry_date');
            $table->string('chart_account_code', 20);
            $table->string('chart_account_label');
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->string('third_party')->nullable();
            $table->string('label')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['organisation_id', 'chart_account_code', 'entry_date']);
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->decimal('tax_percent', 5, 2)->default(0)->after('amount');
            $table->decimal('tax_amount', 15, 2)->default(0)->after('tax_percent')
                ->comment('TVA incluse dans amount (TTC) ; HT = amount − tax_amount');
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE chart_accounts ADD CONSTRAINT chart_accounts_type_check CHECK (type IN ('asset','liability','equity','revenue','expense','treasury'))");
            DB::statement("ALTER TABLE fiscal_years ADD CONSTRAINT fiscal_years_status_check CHECK (status IN ('open','closed'))");
            DB::statement("ALTER TABLE journal_entries ADD CONSTRAINT journal_entries_journal_check CHECK (journal IN ('AC','VE','BQ','CA','OD','PA'))");
            DB::statement('ALTER TABLE journal_lines ADD CONSTRAINT journal_lines_amounts_check CHECK (debit >= 0 AND credit >= 0 AND NOT (debit > 0 AND credit > 0))');
            DB::statement('ALTER TABLE fiscal_years ADD CONSTRAINT fiscal_years_dates_check CHECK (ends_on >= starts_on)');
        }
    }

    public function down(): void
    {
        Schema::table('expenses', fn (Blueprint $table) => $table->dropColumn(['tax_percent', 'tax_amount']));
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('fiscal_years');
        Schema::dropIfExists('accounting_mappings');
        Schema::dropIfExists('chart_accounts');
    }
};
