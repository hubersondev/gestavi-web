<?php

use App\Enums\EmployeeStatus;
use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Enums\PayrollStatus;
use App\Enums\PlanFeature;
use App\Filament\Farm\Pages\Reports\HrReportPage;
use App\Jobs\GenerateReportExport;
use App\Models\Employee;
use App\Models\Farm;
use App\Models\Leave;
use App\Models\Organisation;
use App\Models\Payroll;
use App\Models\Plan;
use App\Models\User;
use Database\Seeders\OrganisationPermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Queue;
use OpenSpout\Reader\XLSX\Reader;

use function Pest\Laravel\actingAs;

/**
 * Rapport RH : effectifs par département et statut, congés chevauchant la période, masse
 * salariale des mois de paie de la période (hors annulés), filtres ferme et département,
 * export du rapport et export détaillé de la paie.
 */
beforeEach(function () {
    $this->seed(OrganisationPermissionSeeder::class);

    $plan = Plan::factory()->create(['features' => collect(PlanFeature::cases())->map->value->all()]);
    $this->organisation = Organisation::factory()->create(['plan_id' => $plan->id]);
    $ownerRole = $this->organisation->organisationRoles()->where('slug', 'owner')->first();

    $this->user = User::factory()->create();
    $this->organisation->addMember($this->user, $ownerRole);

    $this->farmA = Farm::factory()->create(['organisation_id' => $this->organisation->id, 'name' => 'Ferme A']);
    $this->farmB = Farm::factory()->create(['organisation_id' => $this->organisation->id, 'name' => 'Ferme B']);

    actingAs($this->user);
    Filament::setCurrentPanel(Filament::getPanel('farm'));
    Filament::setTenant($this->organisation);

    // Période : du 1er du mois précédent à aujourd'hui (deux mois de paie)
    $this->from = now()->subMonthNoOverflow()->startOfMonth();
    $this->filters = ['date_from' => $this->from->toDateString(), 'date_to' => now()->toDateString(), 'farm_id' => null, 'department' => null];

    $employee = fn (Farm $farm, string $department, EmployeeStatus $status = EmployeeStatus::Active): Employee => Employee::factory()->create([
        'organisation_id' => $this->organisation->id, 'farm_id' => $farm->id, 'department' => $department, 'status' => $status, 'is_resident' => $department === 'securite',
    ]);

    $this->kone = $employee($this->farmA, 'production');
    $this->traore = $employee($this->farmA, 'production', EmployeeStatus::OnLeave);
    $this->yao = $employee($this->farmA, 'securite');
    $this->bamba = $employee($this->farmB, 'administration');
    $employee($this->farmB, 'administration', EmployeeStatus::Terminated);

    $payroll = fn (Employee $e, int $monthsAgo, float $gross, float $deductions, PayrollStatus $status = PayrollStatus::Validated) => Payroll::factory()->create([
        'employee_id' => $e->id,
        'period_year' => (int) now()->subMonthsNoOverflow($monthsAgo)->year,
        'period_month' => (int) now()->subMonthsNoOverflow($monthsAgo)->month,
        'gross_salary' => $gross, 'total_deductions' => $deductions, 'net_salary' => $gross - $deductions, 'cnps_employer' => $gross * 0.1, 'status' => $status,
    ]);

    $payroll($this->kone, 1, 200000, 20000);
    $payroll($this->kone, 0, 200000, 20000, PayrollStatus::Paid);
    $payroll($this->yao, 0, 120000, 10000);
    $payroll($this->bamba, 0, 300000, 50000);
    $payroll($this->traore, 0, 999999, 1, PayrollStatus::Cancelled); // ignoré (un bulletin par employé et par mois)
    $payroll($this->bamba, 3, 300000, 50000); // hors période

    Leave::factory()->create(['employee_id' => $this->traore->id, 'type' => LeaveType::Annual, 'status' => LeaveStatus::Approved, 'start_date' => now()->subDays(3)->toDateString(), 'end_date' => now()->addDays(4)->toDateString(), 'days_count' => 8]);
    Leave::factory()->create(['employee_id' => $this->bamba->id, 'type' => LeaveType::Sick, 'status' => LeaveStatus::Pending, 'start_date' => now()->addDays(10)->toDateString(), 'end_date' => now()->addDays(12)->toDateString(), 'days_count' => 3]); // commence après la période
    Leave::factory()->create(['employee_id' => $this->kone->id, 'type' => LeaveType::Annual, 'status' => LeaveStatus::Pending, 'start_date' => $this->from->copy()->subDays(5)->toDateString(), 'end_date' => $this->from->copy()->addDays(2)->toDateString(), 'days_count' => 8]); // chevauche le début
    Leave::factory()->create(['employee_id' => $this->kone->id, 'type' => LeaveType::Unpaid, 'status' => LeaveStatus::Rejected, 'start_date' => now()->toDateString(), 'end_date' => now()->toDateString(), 'days_count' => 1]); // ignoré
});

function hrReport(array $filters): HrReportPage
{
    $page = app(HrReportPage::class);
    $page->filters = $filters;

    return $page;
}

it('calcule effectifs, congés et masse salariale de la période en ignorant les bulletins annulés et hors période', function () {
    $kpis = hrReport($this->filters)->getKpis();

    expect($kpis['employees_total'])->toBe(5)
        ->and($kpis['employees_active'])->toBe(3)
        ->and($kpis['employees_on_leave'])->toBe(1)
        ->and($kpis['employees_terminated'])->toBe(1)
        ->and($kpis['employees_residents'])->toBe(1)
        ->and($kpis['leaves_total'])->toBe(2)
        ->and($kpis['leaves_pending'])->toBe(1)
        ->and($kpis['leaves_approved_days'])->toBe(8)
        ->and($kpis['payrolls_total'])->toBe(4)
        ->and($kpis['payrolls_paid'])->toBe(1)
        ->and($kpis['gross_payroll'])->toBe(820000.0)
        ->and($kpis['total_deductions'])->toBe(100000.0)
        ->and($kpis['net_payroll'])->toBe(720000.0)
        ->and($kpis['cnps_employer'])->toBe(82000.0)
        ->and($kpis['employer_cost'])->toBe(902000.0);
});

it('ventile les effectifs et la masse salariale par département', function () {
    $page = hrReport($this->filters);

    $headcount = $page->getHeadcountByDepartment()->keyBy('department');
    expect($headcount['production']->total)->toBe(2)
        ->and($headcount['production']->on_leave)->toBe(1)
        ->and($headcount['administration']->terminated)->toBe(1)
        ->and($headcount['securite']->label)->toBe('Sécurité');

    $payroll = $page->getPayrollByDepartment();
    expect($payroll->first()->department)->toBe('production')
        ->and((float) $payroll->first()->gross)->toBe(400000.0)
        ->and($payroll->firstWhere('department', 'administration')->count)->toBe(1);
});

it('filtre par ferme et par département', function () {
    $farmB = hrReport(array_merge($this->filters, ['farm_id' => $this->farmB->id]))->getKpis();
    expect($farmB['employees_total'])->toBe(2)
        ->and($farmB['gross_payroll'])->toBe(300000.0)
        ->and($farmB['leaves_total'])->toBe(0);

    $security = hrReport(array_merge($this->filters, ['department' => 'securite']))->getKpis();
    expect($security['employees_total'])->toBe(1)
        ->and($security['gross_payroll'])->toBe(120000.0)
        ->and($security['leaves_total'])->toBe(0);
});

it('exporte le rapport et le détail de la paie en Excel', function () {
    config(['queue.default' => 'sync']);
    $page = hrReport($this->filters);

    $readRows = function (string $content): array {
        $file = tempnam(sys_get_temp_dir(), 'hr-').'.xlsx';
        file_put_contents($file, $content);
        $reader = new Reader;
        $reader->open($file);
        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
        }
        $reader->close();
        @unlink($file);

        return $rows;
    };

    $report = $readRows($page->buildXlsx());
    expect($report[0][0])->toBe('Effectifs')
        ->and(collect($report)->firstWhere(0, 'Masse salariale brute (XOF)')[1])->toEqual(820000);

    $payroll = $readRows($page->buildPayrollXlsx());
    expect($payroll[0])->toContain('Référence', 'Salaire net', 'CNPS employeur', 'Mode de paiement')
        ->and(count($payroll))->toBe(5)
        ->and(collect($payroll)->skip(1)->sum(fn (array $r) => (float) $r[17]))->toEqual(720000);

    $response = $page->exportPayroll();
    expect($response)->not->toBeNull()
        ->and($response->headers->get('content-disposition'))->toContain('rapport-paie-');
});

it('met l\'export paie en file d\'attente sous sa propre clé', function () {
    Queue::fake();
    config(['queue.default' => 'database']);

    expect(hrReport($this->filters)->exportPayroll())->toBeNull();

    Queue::assertPushed(GenerateReportExport::class, fn (GenerateReportExport $job): bool => $job->report === 'paie' && $job->format === 'xlsx');
    expect(GenerateReportExport::pageClass('paie'))->toBe(HrReportPage::class)
        ->and(GenerateReportExport::pageClass('rh'))->toBe(HrReportPage::class);
});
