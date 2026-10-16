<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Filtres</x-slot>
        {{ $this->filtersForm }}
    </x-filament::section>

    @php
        $kpis = $this->getKpis();
        $fmt = fn ($v) => number_format((float) $v, 0, ',', ' ');
    @endphp

    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
        <x-filament::section>
            <div class="text-center">
                <div class="text-xl font-bold text-gray-700 dark:text-gray-200">{{ $kpis['employees_total'] }}</div>
                <div class="text-xs text-gray-500">Employés</div>
            </div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-center">
                <div class="text-xl font-bold text-success-600">{{ $kpis['employees_active'] }}</div>
                <div class="text-xs text-gray-500">Actifs</div>
            </div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-center">
                <div class="text-xl font-bold text-warning-600">{{ $kpis['employees_on_leave'] }}</div>
                <div class="text-xs text-gray-500">En congé · {{ $kpis['leaves_pending'] }} demande(s) en attente</div>
            </div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-center">
                <div class="text-xl font-bold text-gray-700 dark:text-gray-200">{{ $fmt($kpis['gross_payroll']) }}</div>
                <div class="text-xs text-gray-500">Masse salariale brute (XOF) · {{ $kpis['payrolls_total'] }} bulletin(s)</div>
            </div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-center">
                <div class="text-xl font-bold text-primary-600">{{ $fmt($kpis['net_payroll']) }}</div>
                <div class="text-xs text-gray-500">Masse salariale nette (XOF)</div>
            </div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-center">
                <div class="text-xl font-bold text-gray-700 dark:text-gray-200">{{ $fmt($kpis['employer_cost']) }}</div>
                <div class="text-xs text-gray-500">Coût employeur (XOF), CNPS incluse</div>
            </div>
        </x-filament::section>
    </div>

    <div class="flex flex-wrap justify-end gap-2 mb-4">
        <x-filament::button color="gray" icon="heroicon-o-arrow-down-tray" wire:click="exportExcel">
            Rapport Excel
        </x-filament::button>
        <x-filament::button color="primary" icon="heroicon-o-banknotes" wire:click="exportPayroll">
            Export paie (Excel)
        </x-filament::button>
    </div>

    @php $departments = $this->getHeadcountByDepartment(); @endphp
    <x-filament::section collapsible>
        <x-slot name="heading">Effectifs par département</x-slot>
        <x-slot name="description">Situation actuelle des employés du périmètre, {{ $kpis['employees_residents'] }} résident(s) sur site.</x-slot>
        @if ($departments->isEmpty())
            <p class="text-sm text-gray-500">Aucun employé dans ce périmètre.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-800 dark:text-gray-400">
                        <tr>
                            <th class="px-3 py-2">Département</th>
                            <th class="px-3 py-2 text-right">Actifs</th>
                            <th class="px-3 py-2 text-right">En congé</th>
                            <th class="px-3 py-2 text-right">Suspendus</th>
                            <th class="px-3 py-2 text-right">Résiliés</th>
                            <th class="px-3 py-2 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($departments as $row)
                            <tr class="border-b dark:border-gray-700">
                                <td class="px-3 py-2 font-medium">{{ $row->label }}</td>
                                <td class="px-3 py-2 text-right">{{ $row->active }}</td>
                                <td class="px-3 py-2 text-right">{{ $row->on_leave }}</td>
                                <td class="px-3 py-2 text-right">{{ $row->suspended }}</td>
                                <td class="px-3 py-2 text-right text-gray-500">{{ $row->terminated }}</td>
                                <td class="px-3 py-2 text-right font-bold">{{ $row->total }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-50 dark:bg-gray-800 font-bold">
                        <tr>
                            <td class="px-3 py-2">Total</td>
                            <td class="px-3 py-2 text-right">{{ $kpis['employees_active'] }}</td>
                            <td class="px-3 py-2 text-right">{{ $kpis['employees_on_leave'] }}</td>
                            <td class="px-3 py-2 text-right">{{ $kpis['employees_suspended'] }}</td>
                            <td class="px-3 py-2 text-right">{{ $kpis['employees_terminated'] }}</td>
                            <td class="px-3 py-2 text-right">{{ $kpis['employees_total'] }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </x-filament::section>

    @php $leaves = $this->getLeaves(); @endphp
    <x-filament::section collapsible>
        <x-slot name="heading">Congés sur la période</x-slot>
        <x-slot name="description">{{ $kpis['leaves_approved_days'] }} jour(s) de congé approuvé(s) chevauchant la période.</x-slot>
        @if ($leaves->isEmpty())
            <p class="text-sm text-gray-500">Aucun congé sur la période.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-800 dark:text-gray-400">
                        <tr>
                            <th class="px-3 py-2">Matricule</th>
                            <th class="px-3 py-2">Employé</th>
                            <th class="px-3 py-2">Ferme</th>
                            <th class="px-3 py-2">Type</th>
                            <th class="px-3 py-2">Période</th>
                            <th class="px-3 py-2 text-right">Jours</th>
                            <th class="px-3 py-2">Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($leaves as $leave)
                            <tr class="border-b dark:border-gray-700">
                                <td class="px-3 py-2 text-gray-500">{{ $leave->employee?->employee_number }}</td>
                                <td class="px-3 py-2 font-medium">{{ $leave->employee?->full_name }}</td>
                                <td class="px-3 py-2 text-gray-500">{{ $leave->employee?->farm?->name ?? 'Organisation' }}</td>
                                <td class="px-3 py-2">{{ $leave->type?->getLabel() }}</td>
                                <td class="px-3 py-2">{{ $leave->start_date?->format('d/m/Y') }} → {{ $leave->end_date?->format('d/m/Y') }}</td>
                                <td class="px-3 py-2 text-right">{{ $leave->days_count }}</td>
                                <td class="px-3 py-2 font-medium {{ \App\Filament\Farm\Pages\Reports\HrReportPage::statusColor($leave->status) }}">{{ $leave->status?->getLabel() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>

    @php
        $payrollByDepartment = $this->getPayrollByDepartment();
        $payrolls = $this->getPayrolls();
    @endphp
    <x-filament::section collapsible>
        <x-slot name="heading">Masse salariale</x-slot>
        <x-slot name="description">Bulletins dont le mois de paie tombe dans la période, hors annulés · {{ $kpis['payrolls_paid'] }} payé(s) sur {{ $kpis['payrolls_total'] }}.</x-slot>
        @if ($payrolls->isEmpty())
            <p class="text-sm text-gray-500">Aucun bulletin de paie sur la période.</p>
        @else
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-4">
                <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-3 text-center">
                    <div class="text-lg font-bold text-gray-700 dark:text-gray-200">{{ $fmt($kpis['gross_payroll']) }}</div>
                    <div class="text-xs text-gray-500">Brut (XOF)</div>
                </div>
                <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-3 text-center">
                    <div class="text-lg font-bold text-danger-600">{{ $fmt($kpis['total_deductions']) }}</div>
                    <div class="text-xs text-gray-500">Retenues salariales (XOF)</div>
                </div>
                <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-3 text-center">
                    <div class="text-lg font-bold text-primary-600">{{ $fmt($kpis['net_payroll']) }}</div>
                    <div class="text-xs text-gray-500">Net à payer (XOF)</div>
                </div>
                <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-3 text-center">
                    <div class="text-lg font-bold text-gray-700 dark:text-gray-200">{{ $fmt($kpis['cnps_employer']) }}</div>
                    <div class="text-xs text-gray-500">CNPS employeur (XOF)</div>
                </div>
            </div>

            <div class="overflow-x-auto mb-6">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-800 dark:text-gray-400">
                        <tr>
                            <th class="px-3 py-2">Département</th>
                            <th class="px-3 py-2 text-right">Bulletins</th>
                            <th class="px-3 py-2 text-right">Brut (XOF)</th>
                            <th class="px-3 py-2 text-right">Net (XOF)</th>
                            <th class="px-3 py-2 text-right">Part du brut</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($payrollByDepartment as $row)
                            <tr class="border-b dark:border-gray-700">
                                <td class="px-3 py-2 font-medium">{{ $row->label }}</td>
                                <td class="px-3 py-2 text-right">{{ $row->count }}</td>
                                <td class="px-3 py-2 text-right">{{ $fmt($row->gross) }}</td>
                                <td class="px-3 py-2 text-right">{{ $fmt($row->net) }}</td>
                                <td class="px-3 py-2 text-right text-gray-500">{{ $kpis['gross_payroll'] > 0 ? number_format($row->gross / $kpis['gross_payroll'] * 100, 1, ',', ' ') : '0,0' }} %</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-800 dark:text-gray-400">
                        <tr>
                            <th class="px-3 py-2">Période</th>
                            <th class="px-3 py-2">Matricule</th>
                            <th class="px-3 py-2">Employé</th>
                            <th class="px-3 py-2">Département</th>
                            <th class="px-3 py-2 text-right">Brut</th>
                            <th class="px-3 py-2 text-right">Retenues</th>
                            <th class="px-3 py-2 text-right">Net</th>
                            <th class="px-3 py-2">Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($payrolls as $payroll)
                            <tr class="border-b dark:border-gray-700">
                                <td class="px-3 py-2 text-gray-500">{{ $payroll->getPeriodLabel() }}</td>
                                <td class="px-3 py-2 text-gray-500">{{ $payroll->employee?->employee_number }}</td>
                                <td class="px-3 py-2 font-medium">{{ $payroll->employee?->full_name }}</td>
                                <td class="px-3 py-2">{{ \App\Filament\Farm\Pages\Reports\HrReportPage::departmentLabel($payroll->employee?->department) }}</td>
                                <td class="px-3 py-2 text-right">{{ $fmt($payroll->gross_salary) }}</td>
                                <td class="px-3 py-2 text-right text-danger-600">{{ $fmt($payroll->total_deductions) }}</td>
                                <td class="px-3 py-2 text-right font-medium">{{ $fmt($payroll->net_salary) }}</td>
                                <td class="px-3 py-2 font-medium {{ \App\Filament\Farm\Pages\Reports\HrReportPage::statusColor($payroll->status) }}">{{ $payroll->status?->getLabel() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-50 dark:bg-gray-800 font-bold">
                        <tr>
                            <td class="px-3 py-2" colspan="4">Total ({{ $kpis['payrolls_total'] }} bulletins)</td>
                            <td class="px-3 py-2 text-right">{{ $fmt($kpis['gross_payroll']) }}</td>
                            <td class="px-3 py-2 text-right text-danger-600">{{ $fmt($kpis['total_deductions']) }}</td>
                            <td class="px-3 py-2 text-right">{{ $fmt($kpis['net_payroll']) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
