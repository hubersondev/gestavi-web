<?php

namespace App\Filament\Farm\Pages\Reports;

use App\Enums\EmployeeStatus;
use App\Enums\LeaveStatus;
use App\Enums\PaymentMethod;
use App\Enums\PayrollStatus;
use App\Filament\Farm\Concerns\RequiresOrganisationPermission;
use App\Filament\Farm\Pages\Reports\Concerns\ExportsReport;
use App\Models\Employee;
use App\Models\Farm;
use App\Models\Leave;
use App\Models\Payroll;
use App\Services\CurrentFarm;
use App\Services\CurrentFiscalYear;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * Rapport RH : effectifs par département et statut, congés chevauchant la période, masse
 * salariale des bulletins dont le mois de paie tombe dans la période (brut, retenues, net,
 * charges patronales), avec un export Excel du rapport et un export détaillé de la paie.
 */
class HrReportPage extends Page
{
    use ExportsReport;
    use RequiresOrganisationPermission;

    protected static string $requiredPermission = 'employees.view';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|UnitEnum|null $navigationGroup = 'Rapports';

    protected static ?string $navigationLabel = 'Ressources humaines';

    protected static ?string $title = 'Rapport RH';

    protected static ?int $navigationSort = 8;

    protected string $view = 'filament.farm.pages.reports.hr-report';

    public ?array $filters = [];

    /** @var array<string, mixed>|null */
    protected ?array $kpisCache = null;

    public function mount(): void
    {
        $this->filters = [
            ...app(CurrentFiscalYear::class)->reportPeriod(),
            'farm_id' => null,
            'department' => null,
        ];
    }

    public function reportKey(): string
    {
        return 'rh';
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('filters.date_from')->label('Du')->native(false)->live(),
            DatePicker::make('filters.date_to')->label('Au')->native(false)->live(),
            Select::make('filters.farm_id')
                ->label('Ferme')
                ->options(function (): array {
                    $tenant = Filament::getTenant();

                    return Farm::query()
                        ->where('organisation_id', $tenant?->getKey())
                        ->tap(fn (Builder $q) => app(CurrentFarm::class)->restrictForCurrentUser($q, 'id'))
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray();
                })
                ->placeholder('Toutes les fermes')
                ->searchable()
                ->live(),
            Select::make('filters.department')
                ->label('Département')
                ->options(Employee::DEPARTMENTS)
                ->placeholder('Tous les départements')
                ->live(),
        ])->columns(['sm' => 2, 'lg' => 4]);
    }

    public function updatedFilters(): void
    {
        $this->kpisCache = null;
    }

    // ─── PÉRIMÈTRE ────────────────────────────────────────────────────────────

    /**
     * Organisation, fermes accessibles (les employés rattachés à l'organisation sans ferme
     * sont inclus), ferme filtrée et département.
     */
    private function scope(Builder $query, string $farmColumn, string $departmentColumn): Builder
    {
        $tenant = Filament::getTenant();
        $filters = $this->normalizedFilters();
        $table = $query->getModel()->getTable();

        return $query
            ->where("{$table}.organisation_id", $tenant?->getKey())
            ->tap(fn (Builder $q) => app(CurrentFarm::class)->restrictForCurrentUser($q, $farmColumn, allowOrganisationLevel: true))
            ->when($filters['farm_id'] ?? null, fn (Builder $q, $farmId) => $q->where($farmColumn, $farmId))
            ->when($filters['department'] ?? null, fn (Builder $q, $department) => $q->where($departmentColumn, $department));
    }

    /** Employés du périmètre (tous statuts). */
    public function employeesQuery(): Builder
    {
        return $this->scope(Employee::query(), 'employees.farm_id', 'employees.department');
    }

    /** Congés chevauchant la période, hors annulés et refusés. */
    public function leavesQuery(): Builder
    {
        $filters = $this->normalizedFilters();

        return $this->scope(Leave::query(), 'leaves.farm_id', 'employees.department')
            ->join('employees', 'employees.id', '=', 'leaves.employee_id')
            ->whereDate('leaves.start_date', '<=', $filters['date_to'])
            ->whereDate('leaves.end_date', '>=', $filters['date_from'])
            ->whereIn('leaves.status', [LeaveStatus::Pending->value, LeaveStatus::Approved->value]);
    }

    /** Bulletins dont le mois de paie tombe dans la période, hors annulés. */
    public function payrollsQuery(): Builder
    {
        $filters = $this->normalizedFilters();
        $from = CarbonImmutable::parse($filters['date_from']);
        $to = CarbonImmutable::parse($filters['date_to']);

        return $this->scope(Payroll::query(), 'payrolls.farm_id', 'employees.department')
            ->join('employees', 'employees.id', '=', 'payrolls.employee_id')
            ->whereRaw('(payrolls.period_year * 100 + payrolls.period_month) BETWEEN ? AND ?', [
                $from->year * 100 + $from->month,
                $to->year * 100 + $to->month,
            ])
            ->where('payrolls.status', '!=', PayrollStatus::Cancelled->value);
    }

    // ─── INDICATEURS ──────────────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    public function getKpis(): array
    {
        if ($this->kpisCache !== null) {
            return $this->kpisCache;
        }

        $employees = $this->employeesQuery()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw("COUNT(*) FILTER (WHERE status = 'active') AS active")
            ->selectRaw("COUNT(*) FILTER (WHERE status = 'on_leave') AS on_leave")
            ->selectRaw("COUNT(*) FILTER (WHERE status = 'suspended') AS suspended")
            ->selectRaw("COUNT(*) FILTER (WHERE status = 'terminated') AS terminated")
            ->selectRaw('COUNT(*) FILTER (WHERE is_resident) AS residents')
            ->first();

        $leaves = $this->leavesQuery()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw("COUNT(*) FILTER (WHERE leaves.status = 'pending') AS pending")
            ->selectRaw("COALESCE(SUM(leaves.days_count) FILTER (WHERE leaves.status = 'approved'), 0) AS approved_days")
            ->first();

        $payrolls = $this->payrollsQuery()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('COALESCE(SUM(payrolls.gross_salary), 0) AS gross')
            ->selectRaw('COALESCE(SUM(payrolls.total_deductions), 0) AS deductions')
            ->selectRaw('COALESCE(SUM(payrolls.net_salary), 0) AS net')
            ->selectRaw('COALESCE(SUM(payrolls.cnps_employer), 0) AS cnps_employer')
            ->selectRaw("COUNT(*) FILTER (WHERE payrolls.status = 'paid') AS paid")
            ->first();

        return $this->kpisCache = [
            'employees_total' => (int) $employees->total,
            'employees_active' => (int) $employees->active,
            'employees_on_leave' => (int) $employees->on_leave,
            'employees_suspended' => (int) $employees->suspended,
            'employees_terminated' => (int) $employees->terminated,
            'employees_residents' => (int) $employees->residents,
            'leaves_total' => (int) $leaves->total,
            'leaves_pending' => (int) $leaves->pending,
            'leaves_approved_days' => (int) $leaves->approved_days,
            'payrolls_total' => (int) $payrolls->total,
            'payrolls_paid' => (int) $payrolls->paid,
            'gross_payroll' => (float) $payrolls->gross,
            'total_deductions' => (float) $payrolls->deductions,
            'net_payroll' => (float) $payrolls->net,
            'cnps_employer' => (float) $payrolls->cnps_employer,
            'employer_cost' => (float) $payrolls->gross + (float) $payrolls->cnps_employer,
        ];
    }

    /**
     * Effectifs par département et statut.
     *
     * @return Collection<int, object{department: ?string, label: string, active: int, on_leave: int, suspended: int, terminated: int, total: int}>
     */
    public function getHeadcountByDepartment(): Collection
    {
        return $this->employeesQuery()
            ->selectRaw('employees.department')
            ->selectRaw("COUNT(*) FILTER (WHERE status = 'active') AS active")
            ->selectRaw("COUNT(*) FILTER (WHERE status = 'on_leave') AS on_leave")
            ->selectRaw("COUNT(*) FILTER (WHERE status = 'suspended') AS suspended")
            ->selectRaw("COUNT(*) FILTER (WHERE status = 'terminated') AS terminated")
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('employees.department')
            ->orderBy('employees.department')
            ->get()
            ->map(function (object $row): object {
                $row->label = self::departmentLabel($row->department);

                return $row;
            });
    }

    /**
     * @return Collection<int, Leave>
     */
    public function getLeaves(): Collection
    {
        return $this->leavesQuery()
            ->select('leaves.*')
            ->with(['employee.farm'])
            ->orderBy('leaves.start_date')
            ->get();
    }

    /**
     * @return Collection<int, Payroll>
     */
    public function getPayrolls(): Collection
    {
        return $this->payrollsQuery()
            ->select('payrolls.*')
            ->with(['employee.farm'])
            ->orderBy('payrolls.period_year')
            ->orderBy('payrolls.period_month')
            ->orderBy('employees.last_name')
            ->orderBy('employees.first_name')
            ->get();
    }

    /**
     * Masse salariale par département.
     *
     * @return Collection<int, object{department: ?string, label: string, count: int, gross: float, net: float}>
     */
    public function getPayrollByDepartment(): Collection
    {
        return $this->payrollsQuery()
            ->selectRaw('employees.department')
            ->selectRaw('COUNT(*) AS count')
            ->selectRaw('COALESCE(SUM(payrolls.gross_salary), 0) AS gross')
            ->selectRaw('COALESCE(SUM(payrolls.net_salary), 0) AS net')
            ->groupBy('employees.department')
            ->orderByRaw('SUM(payrolls.gross_salary) DESC')
            ->get()
            ->map(function (object $row): object {
                $row->label = self::departmentLabel($row->department);

                return $row;
            });
    }

    public static function departmentLabel(?string $department): string
    {
        return $department === null ? 'Non défini' : (Employee::DEPARTMENTS[$department] ?? ucfirst($department));
    }

    // ─── EXPORTS ──────────────────────────────────────────────────────────────

    /** Export détaillé de la paie (un bulletin par ligne). */
    public function exportPayroll(): ?StreamedResponse
    {
        return $this->dispatchOrStream('xlsx', 'paie');
    }

    public function buildXlsx(): string
    {
        $kpis = $this->getKpis();

        return $this->xlsxToString(function (callable $addRow) use ($kpis): void {
            $addRow(['Effectifs']);
            $addRow(['Total employés', $kpis['employees_total']]);
            $addRow(['Actifs', $kpis['employees_active']]);
            $addRow(['En congé', $kpis['employees_on_leave']]);
            $addRow(['Suspendus', $kpis['employees_suspended']]);
            $addRow(['Résiliés', $kpis['employees_terminated']]);
            $addRow(['Résidents sur site', $kpis['employees_residents']]);
            $addRow([]);

            $addRow(['Effectifs par département']);
            $addRow(['Département', 'Actifs', 'En congé', 'Suspendus', 'Résiliés', 'Total']);
            foreach ($this->getHeadcountByDepartment() as $row) {
                $addRow([$row->label, (int) $row->active, (int) $row->on_leave, (int) $row->suspended, (int) $row->terminated, (int) $row->total]);
            }
            $addRow([]);

            $addRow(['Congés sur la période']);
            $addRow(['Matricule', 'Employé', 'Ferme', 'Type', 'Du', 'Au', 'Jours', 'Statut']);
            foreach ($this->getLeaves() as $leave) {
                $addRow([
                    $leave->employee?->employee_number,
                    $leave->employee?->full_name,
                    $leave->employee?->farm?->name ?? 'Organisation',
                    $leave->type?->getLabel(),
                    $leave->start_date?->format('d/m/Y'),
                    $leave->end_date?->format('d/m/Y'),
                    (int) $leave->days_count,
                    $leave->status?->getLabel(),
                ]);
            }
            $addRow([]);

            $addRow(['Masse salariale']);
            $addRow(['Bulletins', $kpis['payrolls_total']]);
            $addRow(['Masse salariale brute (XOF)', $kpis['gross_payroll']]);
            $addRow(['Total retenues (XOF)', $kpis['total_deductions']]);
            $addRow(['Masse salariale nette (XOF)', $kpis['net_payroll']]);
            $addRow(['CNPS employeur (XOF)', $kpis['cnps_employer']]);
            $addRow(['Coût employeur (XOF)', $kpis['employer_cost']]);
            $addRow([]);

            $addRow(['Masse salariale par département']);
            $addRow(['Département', 'Bulletins', 'Brut (XOF)', 'Net (XOF)']);
            foreach ($this->getPayrollByDepartment() as $row) {
                $addRow([$row->label, (int) $row->count, (float) $row->gross, (float) $row->net]);
            }
            $addRow([]);

            $addRow(['Bulletins de la période']);
            $addRow(['Période', 'Matricule', 'Employé', 'Département', 'Brut (XOF)', 'Retenues (XOF)', 'Net (XOF)', 'Statut']);
            foreach ($this->getPayrolls() as $payroll) {
                $addRow([
                    $payroll->getPeriodLabel(),
                    $payroll->employee?->employee_number,
                    $payroll->employee?->full_name,
                    self::departmentLabel($payroll->employee?->department),
                    (float) $payroll->gross_salary,
                    (float) $payroll->total_deductions,
                    (float) $payroll->net_salary,
                    $payroll->status?->getLabel(),
                ]);
            }
        });
    }

    /** Un bulletin par ligne, avec le détail des cotisations et retenues. */
    public function buildPayrollXlsx(): string
    {
        return $this->xlsxToString(function (callable $addRow): void {
            $addRow([
                'Référence', 'Période', 'Matricule', 'Employé', 'Ferme', 'Département',
                'Jours travaillés', 'Jours de congé', 'Heures sup.',
                'Salaire brut', 'Brut imposable', 'CNPS salarié', 'CNPS employeur', 'ITS', 'CMU salarié',
                'Autres retenues', 'Total retenues', 'Salaire net',
                'Mode de paiement', 'Statut', 'Payé le',
            ]);

            foreach ($this->getPayrolls() as $payroll) {
                $employee = $payroll->employee;
                $method = $employee?->payment_method ? PaymentMethod::tryFrom($employee->payment_method) : null;

                $addRow([
                    $payroll->getReference(),
                    $payroll->getPeriodLabel(),
                    $employee?->employee_number,
                    $employee?->full_name,
                    $employee?->farm?->name ?? 'Organisation',
                    self::departmentLabel($employee?->department),
                    (int) $payroll->worked_days,
                    (int) $payroll->leave_days,
                    (float) $payroll->overtime_hours,
                    (float) $payroll->gross_salary,
                    (float) $payroll->taxable_gross,
                    (float) $payroll->cnps_employee,
                    (float) $payroll->cnps_employer,
                    (float) $payroll->its,
                    (float) $payroll->cmu_employee,
                    (float) $payroll->other_deductions,
                    (float) $payroll->total_deductions,
                    (float) $payroll->net_salary,
                    $method?->getLabel() ?? ($employee?->payment_method ?? ''),
                    $payroll->status?->getLabel(),
                    $payroll->paid_at?->format('d/m/Y'),
                ]);
            }
        });
    }

    public static function statusColor(EmployeeStatus|LeaveStatus|PayrollStatus|null $status): string
    {
        return match ($status?->color()) {
            'success' => 'text-success-600',
            'warning' => 'text-warning-600',
            'danger' => 'text-danger-600',
            'info' => 'text-info-600',
            default => 'text-gray-500',
        };
    }
}
