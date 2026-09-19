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

        $payroll->items()->whereIn('type', ['base_salary', 'allowance', 'cnps_employee', 'its'])->delete();

        $order = 0;

        // Salaire de base
        PayrollItem::create([
            'payroll_id' => $payroll->id,
            'type' => 'base_salary',
            'label' => 'Salaire de base',
            'amount' => $contract->base_salary,
            'is_deduction' => false,
            'sort_order' => $order++,
        ]);

        // Indemnité transport
        if ($contract->transport_allowance > 0) {
            PayrollItem::create([
                'payroll_id' => $payroll->id,
                'type' => 'allowance',
                'label' => 'Indemnité de transport',
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

        // Calculer brut (sans CNPS/ITS)
        $gross = (float) $contract->base_salary
            + (float) $contract->transport_allowance
            + (float) $contract->housing_allowance
            + (float) $contract->food_allowance;

        // CNPS salarié
        $cnpsEmployee = $this->calculateCnpsEmployee($gross, $params);
        PayrollItem::create([
            'payroll_id' => $payroll->id,
            'type' => 'cnps_employee',
            'label' => sprintf('Cotisation CNPS salarié (%s %%)', $this->formatRate((float) $params->cnps_employee_rate)),
            'amount' => $cnpsEmployee,
            'is_deduction' => true,
            'sort_order' => $order++,
        ]);

        // ITS
        $its = $this->calculateIts($gross - $cnpsEmployee, $params, $fiscalParts);
        if ($its > 0) {
            PayrollItem::create([
                'payroll_id' => $payroll->id,
                'type' => 'its',
                'label' => 'ITS (Impôt sur Traitements et Salaires)',
                'amount' => $its,
                'is_deduction' => true,
                'sort_order' => $order++,
            ]);
        }

        // Recalculer les totaux du bulletin (brut, retenues dont autres retenues, net)
        $payroll->update([
            'cnps_employee' => $cnpsEmployee,
            'cnps_employer' => $this->calculateCnpsEmployer($gross, $params),
            'its' => $its,
            'payroll_parameter_id' => $params->exists ? $params->id : null,
            'cnps_ceiling_applied' => (float) $params->cnps_ceiling,
            'fiscal_parts_applied' => $fiscalParts,
        ]);
        $payroll->recalculate();
    }

    /**
     * Cotisation CNPS salarié (retraite) : taux salarié sur le brut plafonné.
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
     * Détail des charges patronales par branche (bulletin).
     *
     * @return array{pension: float, family: float, work_accident: float}
     */
    public function employerContributionsBreakdown(float $grossSalary, PayrollParameter $params): array
    {
        $gross = max(0, $grossSalary);
        $pensionBase = min($gross, (float) $params->cnps_ceiling);
        $familyBase = min($gross, (float) ($params->cnps_family_ceiling ?? $params->cnps_ceiling));

        return [
            'pension' => round($pensionBase * (float) $params->cnps_employer_pension_rate),
            'family' => round($familyBase * (float) $params->cnps_family_rate),
            'work_accident' => round($familyBase * (float) $params->cnps_work_accident_rate),
        ];
    }

    /**
     * ITS mensuel : barème progressif appliqué à l'assiette
     * (brut − CNPS salarié) × (1 − abattement), moins la réduction pour charges
     * de famille selon les parts fiscales ; jamais négatif, arrondi au franc.
     *
     * @param  float  $taxableBase  Brut diminué de la cotisation CNPS salarié
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
        $high = $targetNet * 1.8; // borne haute large (taux marginal max 32 % + CNPS 6,3 %)
        $mid = $targetNet;

        for ($i = 0; $i < 100; $i++) {
            $mid = ($low + $high) / 2;
            $cnps = $this->calculateCnpsEmployee($mid, $params);
            $its = $this->calculateIts($mid - $cnps, $params, $fiscalParts);
            $net = $mid - $cnps - $its;

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
