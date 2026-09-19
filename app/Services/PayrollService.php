<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollItem;

class PayrollService
{
    // Plafond mensuel CNPS Côte d'Ivoire (1 647 315 FCFA / 12 mois)
    const CNPS_CEILING_MONTHLY = 137_276;

    const CNPS_EMPLOYEE_RATE = 0.063;  // 6.3%

    const CNPS_EMPLOYER_RATE = 0.077;  // 7.7%

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

    public function generateItemsFromContract(Payroll $payroll, \App\Models\Contract $contract): void
    {
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
        $cnpsEmployee = $this->calculateCnpsEmployee($gross);
        PayrollItem::create([
            'payroll_id' => $payroll->id,
            'type' => 'cnps_employee',
            'label' => 'Cotisation CNPS (salarié 6.3%)',
            'amount' => $cnpsEmployee,
            'is_deduction' => true,
            'sort_order' => $order++,
        ]);

        // ITS
        $its = $this->calculateIts($gross - $cnpsEmployee);
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

        // Recalculer les totaux du bulletin
        $payroll->recalculate();
        $payroll->update([
            'gross_salary' => $gross,
            'cnps_employee' => $cnpsEmployee,
            'cnps_employer' => $this->calculateCnpsEmployer($gross),
            'its' => $its,
        ]);
    }

    /**
     * Calcul CNPS salarié — 6.3% du brut plafonné
     */
    public function calculateCnpsEmployee(float $grossSalary): float
    {
        $base = min($grossSalary, self::CNPS_CEILING_MONTHLY);

        return round($base * self::CNPS_EMPLOYEE_RATE, 2);
    }

    /**
     * Calcul CNPS patronal — 7.7% du brut plafonné (informatif)
     */
    public function calculateCnpsEmployer(float $grossSalary): float
    {
        $base = min($grossSalary, self::CNPS_CEILING_MONTHLY);

        return round($base * self::CNPS_EMPLOYER_RATE, 2);
    }

    /**
     * Calcul ITS (Impôt sur Traitements et Salaires) — Barème Côte d'Ivoire
     * Assiette = (brut - CNPS) × 80% (abattement forfaitaire 20%)
     */
    public function calculateIts(float $taxableBase): float
    {
        $assiette = $taxableBase * 0.8;

        return round(match (true) {
            $assiette <= 0 => 0,
            $assiette <= 75_000 => 0,
            $assiette <= 240_000 => ($assiette - 75_000) * 0.16,
            $assiette <= 500_000 => 26_400 + ($assiette - 240_000) * 0.21,
            $assiette <= 1_000_000 => 81_000 + ($assiette - 500_000) * 0.24,
            $assiette <= 2_000_000 => 201_000 + ($assiette - 1_000_000) * 0.28,
            $assiette <= 3_500_000 => 481_000 + ($assiette - 2_000_000) * 0.32,
            default => 961_000 + ($assiette - 3_500_000) * 0.36,
        }, 2);
    }

    /**
     * Rétro-calcul brut à partir d'un salaire net cible.
     * Résolution par dichotomie (convergence en < 60 itérations).
     */
    public function grossFromNet(float $targetNet): float
    {
        if ($targetNet <= 0) {
            return 0;
        }

        $low = $targetNet;
        $high = $targetNet * 1.6; // borne haute large (taux marginal max ~36%)

        for ($i = 0; $i < 100; $i++) {
            $mid = ($low + $high) / 2;
            $cnps = $this->calculateCnpsEmployee($mid);
            $its = $this->calculateIts($mid - $cnps);
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

    public function markAsPaid(Payroll $payroll, int $userId, ?\Carbon\Carbon $date = null): void
    {
        $payroll->markAsPaid($userId, $date);
    }
}
