<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1a1a; line-height: 1.5; }
        .page { padding: 30px 40px; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #2d6a4f; padding-bottom: 12px; margin-bottom: 20px; }
        .org-name { font-size: 16px; font-weight: bold; color: #2d6a4f; }
        .org-meta { font-size: 10px; color: #555; margin-top: 3px; }
        .doc-title { text-align: right; }
        .doc-title h1 { font-size: 15px; font-weight: bold; color: #2d6a4f; text-transform: uppercase; letter-spacing: 1px; }
        .doc-title .ref { font-size: 11px; color: #666; margin-top: 3px; }
        .section { margin-bottom: 16px; }
        .section-title { font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; color: #2d6a4f; border-bottom: 1px solid #c8e6c9; padding-bottom: 3px; margin-bottom: 8px; }
        .grid-2 { width: 100%; border-collapse: collapse; }
        .grid-2 td { width: 50%; padding: 3px 0; vertical-align: top; }
        .label { font-size: 10px; color: #666; display: block; }
        .value { font-weight: bold; font-size: 11px; }
        .salary-table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        .salary-table th { background-color: #e8f5e9; color: #2d6a4f; font-size: 10px; padding: 5px 8px; text-align: left; border: 1px solid #c8e6c9; }
        .salary-table td { padding: 5px 8px; border: 1px solid #e0e0e0; font-size: 11px; }
        .salary-table tfoot td { font-weight: bold; background-color: #e8f5e9; border-top: 2px solid #2d6a4f; }
        .net-row td { font-size: 13px; background-color: #2d6a4f !important; color: #fff; }
        .text-right { text-align: right; }
        .muted { color: #666; font-size: 10px; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 10px; font-weight: bold; background: #d4edda; color: #155724; }
        .note { background: #f9f9f9; border-left: 3px solid #2d6a4f; padding: 8px 10px; font-size: 9.5px; color: #444; margin-top: 10px; }
        .footer { position: fixed; bottom: 15px; left: 40px; right: 40px; border-top: 1px solid #e0e0e0; padding-top: 5px; font-size: 9px; color: #aaa; text-align: center; }
    </style>
</head>
<body>
@php
    $fmt = fn ($value) => number_format((float) $value, 0, ',', ' ');
    $earnings = $payroll->items->where('is_deduction', false);
    $deductions = $payroll->items->where('is_deduction', true);
    $employerTotal = array_sum($employerBreakdown);
@endphp
<div class="page">

    {{-- En-tête --}}
    <div class="header">
        <div>
            <div class="org-name">{{ $organisation?->name }}</div>
            @if($organisation?->address)
                <div class="org-meta">{{ $organisation->address }}</div>
            @endif
            @if($organisation?->phone)
                <div class="org-meta">Tél. : {{ $organisation->phone }}</div>
            @endif
        </div>
        <div class="doc-title">
            <h1>Bulletin de paie</h1>
            <div class="ref">Période : {{ $payroll->getPeriodLabel() }}</div>
            <div class="ref">Réf. {{ $payroll->getReference() }}</div>
            <div class="ref"><span class="badge">{{ $payroll->status->getLabel() }}</span></div>
        </div>
    </div>

    {{-- Salarié --}}
    <div class="section">
        <div class="section-title">Salarié</div>
        <table class="grid-2">
            <tr>
                <td><span class="label">Nom et prénoms</span><span class="value">{{ $employee?->full_name }}</span></td>
                <td><span class="label">Matricule</span><span class="value">{{ $employee?->employee_number }}</span></td>
            </tr>
            <tr>
                <td><span class="label">N° CNPS</span><span class="value">{{ $employee?->cnps_number ?? '—' }}</span></td>
                <td><span class="label">Parts fiscales</span><span class="value">{{ rtrim(rtrim(number_format((float) ($payroll->fiscal_parts_applied ?? $employee?->fiscal_parts ?? 1), 1, ',', ''), '0'), ',') }}</span></td>
            </tr>
            <tr>
                <td><span class="label">Poste</span><span class="value">{{ $contract?->position ?? $employee?->position ?? '—' }}</span></td>
                <td>
                    <span class="label">Contrat</span>
                    <span class="value">
                        @if($contract)
                            {{ $contract->type->getLabel() }} — N° {{ $contract->contract_number ?? $contract->id }}
                            (depuis le {{ $contract->start_date->format('d/m/Y') }})
                        @else
                            —
                        @endif
                    </span>
                </td>
            </tr>
            <tr>
                <td><span class="label">Date d'embauche</span><span class="value">{{ $employee?->hire_date?->format('d/m/Y') ?? '—' }}</span></td>
                <td><span class="label">Jours travaillés / congés</span><span class="value">{{ $payroll->worked_days ?? '—' }} / {{ $payroll->leave_days }}</span></td>
            </tr>
        </table>
    </div>

    {{-- Gains --}}
    <div class="section">
        <div class="section-title">Éléments de rémunération</div>
        <table class="salary-table">
            <thead>
                <tr><th>Libellé</th><th class="text-right">Montant (FCFA)</th></tr>
            </thead>
            <tbody>
                @forelse($earnings as $item)
                    <tr><td>{{ $item->label }}</td><td class="text-right">{{ $fmt($item->amount) }}</td></tr>
                @empty
                    <tr><td colspan="2" class="muted">Aucun élément de rémunération</td></tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr><td>Salaire brut</td><td class="text-right">{{ $fmt($payroll->gross_salary) }}</td></tr>
            </tfoot>
        </table>
    </div>

    {{-- Retenues --}}
    <div class="section">
        <div class="section-title">Retenues salariales</div>
        <table class="salary-table">
            <thead>
                <tr><th>Libellé</th><th class="text-right">Montant (FCFA)</th></tr>
            </thead>
            <tbody>
                @foreach($deductions as $item)
                    <tr><td>{{ $item->label }}</td><td class="text-right">{{ $fmt($item->amount) }}</td></tr>
                @endforeach
                @if((float) $payroll->other_deductions > 0)
                    <tr><td>Autres retenues (avances, saisies, absences)</td><td class="text-right">{{ $fmt($payroll->other_deductions) }}</td></tr>
                @endif
            </tbody>
            <tfoot>
                <tr><td>Total des retenues</td><td class="text-right">{{ $fmt($payroll->total_deductions) }}</td></tr>
                <tr class="net-row"><td>NET À PAYER</td><td class="text-right">{{ $fmt($payroll->net_salary) }} FCFA</td></tr>
            </tfoot>
        </table>
        @if($payroll->isPaid())
            <p class="muted" style="margin-top:6px;">
                Payé le {{ $payroll->paid_at?->format('d/m/Y') }}
                @if($payroll->expense) — dépense {{ $payroll->expense->expense_number }} @endif
            </p>
        @endif
    </div>

    {{-- Charges patronales --}}
    <div class="section">
        <div class="section-title">Cotisations employeur (informatif, non déduites du salaire)</div>
        <table class="salary-table">
            <thead>
                <tr><th>Branche CNPS</th><th class="text-right">Taux</th><th class="text-right">Montant (FCFA)</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td>Retraite (plafond {{ $fmt($parameter->cnps_ceiling) }})</td>
                    <td class="text-right">{{ number_format((float) $parameter->cnps_employer_pension_rate * 100, 2, ',', '') }} %</td>
                    <td class="text-right">{{ $fmt($employerBreakdown['pension']) }}</td>
                </tr>
                <tr>
                    <td>Prestations familiales (plafond {{ $fmt($parameter->cnps_family_ceiling ?? $parameter->cnps_ceiling) }})</td>
                    <td class="text-right">{{ number_format((float) $parameter->cnps_family_rate * 100, 2, ',', '') }} %</td>
                    <td class="text-right">{{ $fmt($employerBreakdown['family']) }}</td>
                </tr>
                <tr>
                    <td>Accident du travail (plafond {{ $fmt($parameter->cnps_family_ceiling ?? $parameter->cnps_ceiling) }})</td>
                    <td class="text-right">{{ number_format((float) $parameter->cnps_work_accident_rate * 100, 2, ',', '') }} %</td>
                    <td class="text-right">{{ $fmt($employerBreakdown['work_accident']) }}</td>
                </tr>
            </tbody>
            <tfoot>
                <tr><td colspan="2">Total charges employeur</td><td class="text-right">{{ $fmt($employerTotal) }}</td></tr>
                <tr><td colspan="2">Coût total employeur (brut + charges)</td><td class="text-right">{{ $fmt((float) $payroll->gross_salary + $employerTotal) }}</td></tr>
            </tfoot>
        </table>
    </div>

    {{-- Paramétrage --}}
    <div class="note">
        <strong>Paramétrage appliqué :</strong> {{ $parameter->getLabel() }}.
        CNPS salarié {{ number_format((float) $parameter->cnps_employee_rate * 100, 2, ',', '') }} % sur brut plafonné à {{ $fmt($payroll->cnps_ceiling_applied ?? $parameter->cnps_ceiling) }} FCFA ;
        ITS : barème progressif sur (brut − CNPS) × {{ number_format((1 - (float) $parameter->its_abatement_rate) * 100, 0) }} %, réduction pour charges de famille selon les parts.
        @if($parameter->source)<br>Source : {{ $parameter->source }}.@endif
    </div>

    @if($payroll->notes)
        <div class="note">{{ $payroll->notes }}</div>
    @endif

</div>

<div class="footer">
    {{ $organisation?->name }} — Bulletin {{ $payroll->getReference() }} — Généré le {{ now()->format('d/m/Y à H:i') }}
</div>
</body>
</html>
