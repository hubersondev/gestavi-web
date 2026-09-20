<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #1a1a1a; line-height: 1.45; }
        .page { padding: 28px 36px; }
        .header { width: 100%; border-collapse: collapse; border-bottom: 2px solid #2d6a4f; margin-bottom: 14px; }
        .header td { vertical-align: top; padding-bottom: 10px; }
        .org-name { font-size: 15px; font-weight: bold; color: #2d6a4f; }
        .org-meta { font-size: 9.5px; color: #555; margin-top: 2px; }
        .doc-title { text-align: right; }
        .doc-title h1 { font-size: 15px; font-weight: bold; color: #2d6a4f; text-transform: uppercase; letter-spacing: 1px; }
        .doc-title .ref { font-size: 10px; color: #666; margin-top: 2px; }
        .section { margin-bottom: 12px; }
        .section-title { font-size: 10.5px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; color: #2d6a4f; border-bottom: 1px solid #c8e6c9; padding-bottom: 2px; margin-bottom: 6px; }
        .grid { width: 100%; border-collapse: collapse; }
        .grid td { padding: 2px 4px 2px 0; vertical-align: top; width: 25%; }
        .label { font-size: 9px; color: #666; display: block; }
        .value { font-weight: bold; font-size: 10.5px; }
        .salary-table { width: 100%; border-collapse: collapse; margin-top: 2px; }
        .salary-table th { background-color: #e8f5e9; color: #2d6a4f; font-size: 9.5px; padding: 4px 6px; text-align: left; border: 1px solid #c8e6c9; }
        .salary-table td { padding: 4px 6px; border: 1px solid #e0e0e0; font-size: 10.5px; }
        .salary-table tfoot td { font-weight: bold; background-color: #f1f8f2; }
        .subtotal td { font-weight: bold; background-color: #f9f9f9; }
        .net-row td { font-size: 12.5px; background-color: #2d6a4f !important; color: #fff; }
        .text-right { text-align: right; }
        .muted { color: #666; font-size: 9.5px; }
        .badge { display: inline-block; padding: 1px 7px; border-radius: 3px; font-size: 9.5px; font-weight: bold; background: #d4edda; color: #155724; }
        .note { background: #f9f9f9; border-left: 3px solid #2d6a4f; padding: 6px 9px; font-size: 9px; color: #444; margin-top: 8px; }
        .footer { position: fixed; bottom: 14px; left: 36px; right: 36px; border-top: 1px solid #e0e0e0; padding-top: 4px; font-size: 8.5px; color: #aaa; text-align: center; }
        .two-col { width: 100%; border-collapse: collapse; }
        .two-col > tbody > tr > td { width: 50%; vertical-align: top; padding: 0; }
        .two-col > tbody > tr > td + td { padding-left: 10px; }
    </style>
</head>
<body>
@php
    $fmt = fn ($value) => number_format((float) $value, 0, ',', ' ');
    $pct = fn ($rate, int $decimals = 2) => number_format((float) $rate * 100, $decimals, ',', '');
    $earnings = $payroll->items->where('is_deduction', false);
    $deductions = $payroll->items->where('is_deduction', true);
    $employerTotal = $employerBreakdown['total'] ?? array_sum(array_filter($employerBreakdown, 'is_numeric'));
    $taxableGross = (float) ($payroll->taxable_gross ?: $payroll->gross_salary);
    $monthlyHours = $contract?->working_hours_per_week ? round($contract->working_hours_per_week * 52 / 12, 2) : null;
    $paymentMethod = $employee?->payment_method;
    $paymentMethodLabel = $paymentMethod instanceof \BackedEnum
        ? (method_exists($paymentMethod, 'getLabel') ? $paymentMethod->getLabel() : $paymentMethod->value)
        : $paymentMethod;
    $fiscalParts = rtrim(rtrim(number_format((float) ($payroll->fiscal_parts_applied ?? $employee?->fiscal_parts ?? 1), 1, ',', ''), '0'), ',');
@endphp
<div class="page">

    {{-- En-tête --}}
    <table class="header">
        <tr>
            <td>
                <div class="org-name">{{ $organisation?->name }}</div>
                @if($organisation?->address)<div class="org-meta">{{ $organisation->address }}</div>@endif
                @if($organisation?->phone)<div class="org-meta">Tél. : {{ $organisation->phone }}</div>@endif
                @if($organisation?->cnps_employer_number)<div class="org-meta">N° CNPS employeur : {{ $organisation->cnps_employer_number }}</div>@endif
            </td>
            <td class="doc-title">
                <h1>Bulletin de paie</h1>
                <div class="ref">Période du {{ $payroll->getPeriodStart()->format('d/m/Y') }} au {{ $payroll->getPeriodEnd()->format('d/m/Y') }}</div>
                <div class="ref">Réf. {{ $payroll->getReference() }} — édité le {{ now()->format('d/m/Y') }}</div>
                <div class="ref"><span class="badge">{{ $payroll->status->getLabel() }}</span></div>
            </td>
        </tr>
    </table>

    {{-- Salarié --}}
    <div class="section">
        <div class="section-title">Salarié</div>
        <table class="grid">
            <tr>
                <td colspan="2"><span class="label">Nom et prénoms</span><span class="value">{{ $employee?->full_name }}</span></td>
                <td><span class="label">Matricule</span><span class="value">{{ $employee?->employee_number ?? '—' }}</span></td>
                <td><span class="label">N° CNPS</span><span class="value">{{ $employee?->cnps_number ?? '—' }}</span></td>
            </tr>
            <tr>
                <td colspan="2"><span class="label">Fonction</span><span class="value">{{ $contract?->position ?? $employee?->position ?? '—' }}</span></td>
                <td><span class="label">Catégorie</span><span class="value">{{ $contract?->professional_category ?? '—' }}</span></td>
                <td><span class="label">Service</span><span class="value">{{ $employee?->department ?? '—' }}</span></td>
            </tr>
            <tr>
                <td><span class="label">Date d'entrée</span><span class="value">{{ $employee?->hire_date?->format('d/m/Y') ?? $contract?->start_date?->format('d/m/Y') ?? '—' }}</span></td>
                <td><span class="label">Nationalité</span><span class="value">{{ $employee?->nationality ?? '—' }}</span></td>
                <td><span class="label">Parts fiscales</span><span class="value">{{ $fiscalParts }}</span></td>
                <td><span class="label">Contrat</span><span class="value">{{ $contract ? $contract->type->getLabel().' n° '.($contract->contract_number ?? $contract->id) : '—' }}</span></td>
            </tr>
            <tr>
                <td><span class="label">Base horaire mensuelle</span><span class="value">{{ $monthlyHours ? number_format($monthlyHours, 2, ',', ' ').' h' : '—' }}</span></td>
                <td><span class="label">Jours travaillés / congés</span><span class="value">{{ $payroll->worked_days ?? '—' }} / {{ $payroll->leave_days }}</span></td>
                <td><span class="label">Mode de paiement</span><span class="value">{{ $paymentMethodLabel ?? '—' }}</span></td>
                <td><span class="label">Adresse</span><span class="value">{{ $employee?->address ?? '—' }}</span></td>
            </tr>
        </table>
    </div>

    {{-- Gains et retenues --}}
    <div class="section">
        <div class="section-title">Éléments de paie</div>
        <table class="salary-table">
            <thead>
                <tr><th style="width:62%">Libellé</th><th class="text-right">Retenues (FCFA)</th><th class="text-right">Gains (FCFA)</th></tr>
            </thead>
            <tbody>
                @forelse($earnings as $item)
                    <tr><td>{{ $item->label }}</td><td></td><td class="text-right">{{ $fmt($item->amount) }}</td></tr>
                @empty
                    <tr><td colspan="3" class="muted">Aucun élément de rémunération</td></tr>
                @endforelse
                <tr class="subtotal"><td>Total imposable</td><td></td><td class="text-right">{{ $fmt($taxableGross) }}</td></tr>
                @foreach($deductions as $item)
                    <tr><td>{{ $item->label }}</td><td class="text-right">{{ $fmt($item->amount) }}</td><td></td></tr>
                @endforeach
                @if((float) $payroll->other_deductions > 0)
                    <tr><td>Autres retenues (avances, saisies, absences)</td><td class="text-right">{{ $fmt($payroll->other_deductions) }}</td><td></td></tr>
                @endif
            </tbody>
            <tfoot>
                <tr><td>Total des gains / total des retenues</td><td class="text-right">{{ $fmt($payroll->total_deductions) }}</td><td class="text-right">{{ $fmt($payroll->gross_salary) }}</td></tr>
                <tr class="net-row"><td>NET À PAYER</td><td></td><td class="text-right">{{ $fmt($payroll->net_salary) }} FCFA</td></tr>
            </tfoot>
        </table>
        @if($payroll->isPaid())
            <p class="muted" style="margin-top:4px;">
                Payé le {{ $payroll->paid_at?->format('d/m/Y') }}
                @if($payroll->expense) — dépense {{ $payroll->expense->expense_number }} @endif
            </p>
        @endif
    </div>

    {{-- Charges patronales et récapitulatif salarié --}}
    <table class="two-col">
        <tr>
            <td>
                <div class="section">
                    <div class="section-title">Retenues patronales (informatif)</div>
                    <table class="salary-table">
                        <thead><tr><th>Branche</th><th class="text-right">Taux</th><th class="text-right">Montant</th></tr></thead>
                        <tbody>
                            <tr><td>Prestations familiales (plaf. {{ $fmt($parameter->cnps_family_ceiling ?? $parameter->cnps_ceiling) }})</td><td class="text-right">{{ $pct($parameter->cnps_family_rate) }} %</td><td class="text-right">{{ $fmt($employerBreakdown['family'] ?? 0) }}</td></tr>
                            <tr><td>Accident du travail (plaf. {{ $fmt($parameter->cnps_family_ceiling ?? $parameter->cnps_ceiling) }})</td><td class="text-right">{{ $pct($parameter->cnps_work_accident_rate) }} %</td><td class="text-right">{{ $fmt($employerBreakdown['work_accident'] ?? 0) }}</td></tr>
                            <tr><td>Caisse de retraite (plaf. {{ $fmt($parameter->cnps_ceiling) }})</td><td class="text-right">{{ $pct($parameter->cnps_employer_pension_rate) }} %</td><td class="text-right">{{ $fmt($employerBreakdown['pension'] ?? 0) }}</td></tr>
                            <tr><td>Taxe d'apprentissage (FDFP)</td><td class="text-right">{{ $pct($parameter->fdfp_apprenticeship_rate ?? 0) }} %</td><td class="text-right">{{ $fmt($employerBreakdown['fdfp_apprenticeship'] ?? 0) }}</td></tr>
                            <tr><td>Formation professionnelle continue (FDFP)</td><td class="text-right">{{ $pct($parameter->fdfp_training_rate ?? 0) }} %</td><td class="text-right">{{ $fmt($employerBreakdown['fdfp_training'] ?? 0) }}</td></tr>
                            <tr><td>Couverture Maladie Universelle</td><td class="text-right">forfait</td><td class="text-right">{{ $fmt($employerBreakdown['cmu'] ?? 0) }}</td></tr>
                        </tbody>
                        <tfoot>
                            <tr><td colspan="2">Total charges employeur</td><td class="text-right">{{ $fmt($employerTotal) }}</td></tr>
                            <tr><td colspan="2">Coût total employeur</td><td class="text-right">{{ $fmt((float) $payroll->gross_salary + $employerTotal) }}</td></tr>
                        </tfoot>
                    </table>
                </div>
            </td>
            <td>
                <div class="section">
                    <div class="section-title">Retenues salariales</div>
                    <table class="salary-table">
                        <thead><tr><th>Nature</th><th class="text-right">Base</th><th class="text-right">Montant</th></tr></thead>
                        <tbody>
                            <tr><td>ITS ({{ $fiscalParts }} part{{ $fiscalParts === '1' ? '' : 's' }})</td><td class="text-right">{{ $fmt($taxableGross) }}</td><td class="text-right">{{ $fmt($payroll->its) }}</td></tr>
                            <tr><td>Caisse de retraite ({{ $pct($parameter->cnps_employee_rate) }} %)</td><td class="text-right">{{ $fmt(min($taxableGross, (float) ($payroll->cnps_ceiling_applied ?? $parameter->cnps_ceiling))) }}</td><td class="text-right">{{ $fmt($payroll->cnps_employee) }}</td></tr>
                            <tr><td>Couverture Maladie Universelle</td><td class="text-right">forfait</td><td class="text-right">{{ $fmt($payroll->cmu_employee) }}</td></tr>
                        </tbody>
                        <tfoot>
                            <tr><td colspan="2">Total retenues salariales</td><td class="text-right">{{ $fmt($payroll->total_deductions) }}</td></tr>
                        </tfoot>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    {{-- Paramétrage --}}
    <div class="note">
        <strong>Paramétrage appliqué :</strong> {{ $parameter->getLabel() }}.
        Prime de transport exonérée jusqu'à {{ $fmt($parameter->transport_exemption_ceiling ?? 0) }} FCFA ;
        CNPS salarié {{ $pct($parameter->cnps_employee_rate) }} % du brut imposable plafonné à {{ $fmt($payroll->cnps_ceiling_applied ?? $parameter->cnps_ceiling) }} FCFA ;
        ITS : barème progressif sur le brut imposable{{ (float) $parameter->its_abatement_rate > 0 ? ' après abattement de '.$pct($parameter->its_abatement_rate, 0).' %' : '' }}, réduction pour charges de famille selon les parts ;
        CMU {{ $fmt(($parameter->cmu_employee_amount ?? 0) + ($parameter->cmu_employer_amount ?? 0)) }} FCFA/mois dont {{ $fmt($parameter->cmu_employee_amount ?? 0) }} à la charge du salarié.
        @if($parameter->source)<br>Source : {{ $parameter->source }}.@endif
    </div>

    @if($payroll->notes)
        <div class="note">{{ $payroll->notes }}</div>
    @endif

    <p class="muted" style="margin-top:10px;">Pour vous aider à faire valoir vos droits, conservez ce bulletin de paie sans limitation de durée.</p>

</div>

<div class="footer">
    {{ $organisation?->name }} — Bulletin {{ $payroll->getReference() }} — Généré le {{ now()->format('d/m/Y à H:i') }}
</div>
</body>
</html>
