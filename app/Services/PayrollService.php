<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\PayrollParameter;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Calculs de paie (Côte d'Ivoire) pilotés par un paramétrage versionné
 * (PayrollParameter) : aucun taux ni plafond n'est codé en dur.
 */
class PayrollService
{
    /**
     * Paramétrage applicable à une organisation à une date donnée : le plus récent
     * paramétrage propre à l'organisation dont la date d'effet est passée, sinon le
     * paramétrage légal par défaut (organisation NULL), sinon les valeurs embarquées.
     */
    public function parametersFor(?int $organisationId, ?CarbonInterface $date = null): PayrollParameter
    {
        $date ??= now();

        if ($organisationId !== null) {
            $own = PayrollParameter::query()
                ->where('organisation_id', $organisationId)
                ->effectiveAt($date)
                ->with('taxBrackets')
                ->first();

            if ($own) {
                return $own;
            }
        }

        $legal = PayrollParameter::query()
            ->whereNull('organisation_id')
            ->effectiveAt($date)
            ->with('taxBrackets')
            ->first();

        return $legal ?? PayrollParameter::legalDefaults();
    }

    public function generatePayroll(Employee $employee, int $year, int $month): Payroll
    {
        // Vérifier qu'il n'en existe pas déjà un
        $existing = Payroll::where('employee_id', $employee->id)
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->first();

        if ($existing) {
            return $existing;
        }

        $contract = $employee->activeContract;

        $payroll = Payroll::create([
            'employee_id' => $employee->id,
            'contract_id' => $contract?->id,
            'period_year' => $year,
            'period_month' => $month,
            'status' => 'draft',
        ]);

        if ($contract) {
            $this->generateItemsFromContract($payroll, $contract);
        }

        return $payroll;
    }

    public function generateItemsFromContract(Payroll $payroll, Contract $contract): void
    {
        $employee = $payroll->employee ?? $contract->employee;
        $params = $this->parametersFor(
            $employee?->organisation_id,
            Carbon::create($payroll->period_year, $payroll->period_month, 1),
        );
        $fiscalParts = (float) ($employee?->fiscal_parts ?? 1);

        $payroll->items()->whereIn('type', ['base_salary', 'allowance', 'cnps_employee', 'its', 'cmu_employee'])->delete();

        $order = 0;

        // Salaire de base : catégoriel + sursalaire si la grille conventionnelle est renseignée
        if ($contract->hasCategorySalarySplit()) {
            PayrollItem::create([
                'payroll_id' => $payroll->id,
                'type' => 'base_salary',
                'label' => 'Salaire catégoriel'.($contract->professional_category ? ' (cat. '.$contract->professional_category.')' : ''),
                'amount' => $contract->category_salary,
                'is_deduction' => false,
                'sort_order' => $order++,
            ]);

            PayrollItem::create([
                'payroll_id' => $payroll->id,
                'type' => 'base_salary',
                'label' => 'Sursalaire',
                'amount' => $contract->getOverSalary(),
                'is_deduction' => false,
                'sort_order' => $order++,
            ]);
        } else {
            PayrollItem::create([
                'payroll_id' => $payroll->id,
                'type' => 'base_salary',
                'label' => 'Salaire de base',
                'amount' => $contract->base_salary,
                'is_deduction' => false,
                'sort_order' => $order++,
            ]);
        }

        // Prime de transport : exonérée d'ITS et de CNPS jusqu'au plafond légal
        if ($contract->transport_allowance > 0) {
            $exempt = min((float) $contract->transport_allowance, (float) $params->transport_exemption_ceiling);

            PayrollItem::create([
                'payroll_id' => $payroll->id,
                'type' => 'allowance',
                'label' => $exempt >= (float) $contract->transport_allowance
                    ? 'Prime de transport (non imposable)'
                    : sprintf('Prime de transport (exonérée jusqu\'à %s FCFA)', number_format($exempt, 0, ',', ' ')),
                'amount' => $contract->transport_allowance,
                'is_deduction' => false,
                'sort_order' => $order++,
            ]);
        }

        // Indemnité logement
        if ($contract->housing_allowance > 0) {
            PayrollItem::create([
                'payroll_id' => $payroll->id,
                'type' => 'allowance',
                'label' => 'Indemnité de logement',
                'amount' => $contract->housing_allowance,
                'is_deduction' => false,
                'sort_order' => $order++,
            ]);
        }

        // Indemnité panier
        if ($contract->food_allowance > 0) {
            PayrollItem::create([
                'payroll_id' => $payroll->id,
                'type' => 'allowance',
                'label' => 'Indemnité de panier',
                'amount' => $contract->food_allowance,
                'is_deduction' => false,
                'sort_order' => $order++,
            ]);
        }

        $computed = $this->computeFromAmounts(
            (float) $contract->base_salary,
            (float) $contract->transport_allowance,
            (float) $contract->housing_allowance,
            (float) $contract->food_allowance,
            $params,
            $fiscalParts,
        );

        // CNPS salarié (retraite) sur le brut imposable plafonné
        PayrollItem::create([
            'payroll_id' => $payroll->id,
            'type' => 'cnps_employee',
            'label' => sprintf('Cotisation CNPS salarié (%s %%)', $this->formatRate((float) $params->cnps_employee_rate)),
            'amount' => $computed['cnps_employee'],
            'is_deduction' => true,
            'sort_order' => $order++,
        ]);

        // ITS sur le brut imposable
        if ($computed['its'] > 0) {
            PayrollItem::create([
                'payroll_id' => $payroll->id,
                'type' => 'its',
                'label' => 'ITS (Impôt sur Traitements et Salaires)',
                'amount' => $computed['its'],
                'is_deduction' => true,
                'sort_order' => $order++,
            ]);
        }

        // Couverture Maladie Universelle, part salariale (forfait mensuel)
        if ($computed['cmu_employee'] > 0) {
            PayrollItem::create([
                'payroll_id' => $payroll->id,
                'type' => 'cmu_employee',
                'label' => 'Couverture Maladie Universelle (part salariale)',
                'amount' => $computed['cmu_employee'],
                'is_deduction' => true,
                'sort_order' => $order++,
            ]);
        }

        $employer = $this->employerContributionsBreakdown($computed['taxable_gross'], $params);

        // Recalculer les totaux du bulletin (brut, retenues dont autres retenues, net)
        $payroll->update([
            'taxable_gross' => $computed['taxable_gross'],
            'cnps_employee' => $computed['cnps_employee'],
            'cnps_employer' => $employer['pension'] + $employer['family'] + $employer['work_accident'],
            'employer_contributions' => $employer,
            'its' => $computed['its'],
            'cmu_employee' => $computed['cmu_employee'],
            'payroll_parameter_id' => $params->exists ? $params->id : null,
            'cnps_ceiling_applied' => (float) $params->cnps_ceiling,
            'fiscal_parts_applied' => $fiscalParts,
        ]);
        $payroll->recalculate();
    }

    /**
     * Calcul complet d'un bulletin à partir des éléments contractuels :
     * total des gains, brut imposable (la prime de transport est exonérée jusqu'au
     * plafond), CNPS salarié et ITS sur le brut imposable, CMU forfaitaire, net.
     *
     * @return array{gross: float, taxable_gross: float, exempt_transport: float, cnps_employee: float, its: float, cmu_employee: float, total_deductions: float, net: float}
     */
    public function computeFromAmounts(
        float $baseSalary,
        float $transportAllowance,
        float $housingAllowance,
        float $foodAllowance,
        ?PayrollParameter $params = null,
        float $fiscalParts = 1.0,
    ): array {
        $params ??= $this->parametersFor(null);

        $exemptTransport = min(max(0, $transportAllowance), (float) $params->transport_exemption_ceiling);
        $gross = max(0, $baseSalary) + max(0, $transportAllowance) + max(0, $housingAllowance) + max(0, $foodAllowance);
        $taxableGross = $gross - $exemptTransport;

        $cnpsEmployee = $this->calculateCnpsEmployee($taxableGross, $params);
        $its = $this->calculateIts($taxableGross, $params, $fiscalParts);
        $cmuEmployee = round((float) $params->cmu_employee_amount);
        $deductions = $cnpsEmployee + $its + $cmuEmployee;

        return [
            'gross' => round($gross, 2),
            'taxable_gross' => round($taxableGross, 2),
            'exempt_transport' => round($exemptTransport, 2),
            'cnps_employee' => $cnpsEmployee,
            'its' => $its,
            'cmu_employee' => $cmuEmployee,
            'total_deductions' => $deductions,
            'net' => round(max(0, $gross - $deductions), 2),
        ];
    }

    /**
     * Cotisation CNPS salarié (retraite) : taux salarié sur le brut imposable plafonné.
     */
    public function calculateCnpsEmployee(float $grossSalary, ?PayrollParameter $params = null): float
    {
        $params ??= $this->parametersFor(null);
        $base = min(max(0, $grossSalary), (float) $params->cnps_ceiling);

        return round($base * (float) $params->cnps_employee_rate);
    }

    /**
     * Charges patronales CNPS (informatif) : retraite sur le plafond retraite,
     * prestations familiales et accident du travail sur leur propre plafond.
     */
    public function calculateCnpsEmployer(float $grossSalary, ?PayrollParameter $params = null): float
    {
        $params ??= $this->parametersFor(null);
        $gross = max(0, $grossSalary);

        $pensionBase = min($gross, (float) $params->cnps_ceiling);
        $familyBase = min($gross, (float) ($params->cnps_family_ceiling ?? $params->cnps_ceiling));

        return round(
            $pensionBase * (float) $params->cnps_employer_pension_rate
            + $familyBase * (float) $params->cnps_family_rate
            + $familyBase * (float) $params->cnps_work_accident_rate
        );
    }

    /**
     * Détail des charges patronales (bulletin) : branches CNPS avec leurs plafonds,
     * FDFP (taxe d'apprentissage et formation continue) sur le brut imposable, CMU forfaitaire.
     *
     * @return array{pension: float, family: float, work_accident: float, fdfp_apprenticeship: float, fdfp_training: float, cmu: float, total: float}
     */
    public function employerContributionsBreakdown(float $taxableGross, PayrollParameter $params): array
    {
        $gross = max(0, $taxableGross);
        $pensionBase = min($gross, (float) $params->cnps_ceiling);
        $familyBase = min($gross, (float) ($params->cnps_family_ceiling ?? $params->cnps_ceiling));

        $breakdown = [
            'pension' => round($pensionBase * (float) $params->cnps_employer_pension_rate),
            'family' => round($familyBase * (float) $params->cnps_family_rate),
            'work_accident' => round($familyBase * (float) $params->cnps_work_accident_rate),
            'fdfp_apprenticeship' => round($gross * (float) $params->fdfp_apprenticeship_rate),
            'fdfp_training' => round($gross * (float) $params->fdfp_training_rate),
            'cmu' => round((float) $params->cmu_employer_amount),
        ];

        return $breakdown + ['total' => array_sum($breakdown)];
    }

    /**
     * ITS mensuel (réforme 2024) : barème progressif appliqué directement au brut
     * imposable (l'abattement paramétrable vaut 0 par défaut), moins la réduction pour
     * charges de famille selon les parts fiscales ; jamais négatif, arrondi au franc.
     *
     * @param  float  $taxableBase  Brut imposable (gains hors prime de transport exonérée)
     */
    public function calculateIts(float $taxableBase, ?PayrollParameter $params = null, float $fiscalParts = 1.0): float
    {
        $params ??= $this->parametersFor(null);

        $assiette = max(0, $taxableBase) * (1 - (float) $params->its_abatement_rate);

        $tax = 0.0;
        foreach ($params->taxBrackets as $bracket) {
            $tax += $bracket->taxFor($assiette);
        }

        $tax -= $params->familyReductionFor($fiscalParts);

        return round(max(0, $tax));
    }

    /**
     * Rétro-calcul brut à partir d'un salaire net cible.
     * Résolution par dichotomie (convergence en < 60 itérations).
     */
    public function grossFromNet(float $targetNet, ?PayrollParameter $params = null, float $fiscalParts = 1.0): float
    {
        if ($targetNet <= 0) {
            return 0;
        }

        $params ??= $this->parametersFor(null);

        $low = $targetNet;
        $high = $targetNet * 1.8 + (float) $params->cmu_employee_amount; // borne haute large (taux marginal max 32 % + CNPS 6,3 % + CMU)
        $mid = $targetNet;

        for ($i = 0; $i < 100; $i++) {
            $mid = ($low + $high) / 2;
            $net = $this->computeFromAmounts($mid, 0, 0, 0, $params, $fiscalParts)['net'];

            if (abs($net - $targetNet) < 0.5) {
                break;
            }

            if ($net < $targetNet) {
                $low = $mid;
            } else {
                $high = $mid;
            }
        }

        return round($mid);
    }

    public function validatePayroll(Payroll $payroll, int $userId): void
    {
        $payroll->recalculate();
        $payroll->validate($userId);
    }

    /**
     * Paiement du bulletin : crée la dépense « salaires » et débite le compte.
     */
    public function markAsPaid(Payroll $payroll, int $userId, int $accountId, ?CarbonInterface $date = null): void
    {
        $payroll->markAsPaid($userId, $accountId, $date);
    }

    private function formatRate(float $rate): string
    {
        return rtrim(rtrim(number_format($rate * 100, 2, ',', ''), '0'), ',');
    }
}
