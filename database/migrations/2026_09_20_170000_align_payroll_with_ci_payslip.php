<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Alignement de la paie sur un bulletin ivoirien réel (juillet 2026) :
 * - ITS assis sur le brut imposable (réforme 2024 : plus d'abattement de 20 % ni de déduction CNPS) ;
 * - prime de transport exonérée jusqu'à un plafond ; total imposable distinct du total des gains ;
 * - Couverture Maladie Universelle (part salariale et patronale) ;
 * - FDFP (taxe d'apprentissage et formation professionnelle continue) dans les charges patronales ;
 * - salaire catégoriel / sursalaire et catégorie professionnelle sur le contrat ;
 * - numéro CNPS de l'employeur ; instantané des charges patronales sur le bulletin.
 */
return new class extends Migration
{
    private const array ITEM_TYPES = [
        'base_salary', 'bonus', 'allowance', 'overtime', 'deduction', 'cnps_employee', 'cnps_employer', 'its', 'cmu_employee',
    ];

    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->string('cnps_employer_number', 30)->nullable()->after('phone');
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->string('professional_category', 10)->nullable()->after('position')->comment('Catégorie professionnelle (convention collective), ex. 7B');
            $table->decimal('category_salary', 12, 2)->nullable()->after('base_salary')->comment('Salaire catégoriel ; sursalaire = base_salary − category_salary');
        });

        Schema::table('payroll_parameters', function (Blueprint $table) {
            $table->decimal('transport_exemption_ceiling', 12, 2)->default(30000)->after('cnps_family_ceiling')->comment('Prime de transport exonérée d\'ITS et de CNPS jusqu\'à ce montant');
            $table->decimal('cmu_employee_amount', 10, 2)->default(500)->after('transport_exemption_ceiling');
            $table->decimal('cmu_employer_amount', 10, 2)->default(500)->after('cmu_employee_amount');
            $table->decimal('fdfp_apprenticeship_rate', 6, 4)->default(0.004)->after('cmu_employer_amount');
            $table->decimal('fdfp_training_rate', 6, 4)->default(0.006)->after('fdfp_apprenticeship_rate');
        });

        // Réforme ITS 2024 : barème appliqué directement au brut imposable
        DB::table('payroll_parameters')->whereNull('organisation_id')->update(['its_abatement_rate' => 0]);

        Schema::table('payrolls', function (Blueprint $table) {
            $table->decimal('taxable_gross', 12, 2)->default(0)->after('gross_salary')->comment('Total imposable (gains hors part exonérée de la prime de transport)');
            $table->decimal('cmu_employee', 10, 2)->default(0)->after('its');
            $table->jsonb('employer_contributions')->nullable()->after('cnps_employer')->comment('Instantané des charges patronales par branche au moment du calcul');
        });

        if (DB::getDriverName() !== 'sqlite') {
            $types = implode(', ', array_map(fn (string $type): string => "'{$type}'", self::ITEM_TYPES));

            DB::statement('ALTER TABLE payroll_items DROP CONSTRAINT IF EXISTS payroll_items_type_check');
            DB::statement("ALTER TABLE payroll_items ADD CONSTRAINT payroll_items_type_check CHECK (type IN ({$types}))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $types = implode(', ', array_map(fn (string $type): string => "'{$type}'", array_diff(self::ITEM_TYPES, ['cmu_employee'])));

            DB::statement('ALTER TABLE payroll_items DROP CONSTRAINT IF EXISTS payroll_items_type_check');
            DB::statement("ALTER TABLE payroll_items ADD CONSTRAINT payroll_items_type_check CHECK (type IN ({$types}))");
        }

        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn(['taxable_gross', 'cmu_employee', 'employer_contributions']);
        });

        Schema::table('payroll_parameters', function (Blueprint $table) {
            $table->dropColumn(['transport_exemption_ceiling', 'cmu_employee_amount', 'cmu_employer_amount', 'fdfp_apprenticeship_rate', 'fdfp_training_rate']);
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn(['professional_category', 'category_salary']);
        });

        Schema::table('organisations', function (Blueprint $table) {
            $table->dropColumn('cnps_employer_number');
        });
    }
};
