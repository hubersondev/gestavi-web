<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #1a1a1a;
            line-height: 1.5;
        }

        .page {
            padding: 30px 40px;
        }

        /* En-tête */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #2d6a4f;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }

        .org-name {
            font-size: 16px;
            font-weight: bold;
            color: #2d6a4f;
        }

        .org-meta {
            font-size: 10px;
            color: #555;
            margin-top: 3px;
        }

        .doc-title {
            text-align: right;
        }

        .doc-title h1 {
            font-size: 15px;
            font-weight: bold;
            color: #2d6a4f;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .doc-title .contract-number {
            font-size: 11px;
            color: #666;
            margin-top: 3px;
        }

        /* Sections */
        .section {
            margin-bottom: 16px;
        }

        .section-title {
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #2d6a4f;
            border-bottom: 1px solid #c8e6c9;
            padding-bottom: 3px;
            margin-bottom: 8px;
        }

        /* Grille 2 colonnes */
        .grid-2 {
            width: 100%;
            border-collapse: collapse;
        }

        .grid-2 td {
            width: 50%;
            padding: 3px 0;
            vertical-align: top;
        }

        .label {
            font-size: 10px;
            color: #666;
            display: block;
        }

        .value {
            font-weight: bold;
            font-size: 11px;
        }

        /* Tableau salaires */
        .salary-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }

        .salary-table th {
            background-color: #e8f5e9;
            color: #2d6a4f;
            font-size: 10px;
            padding: 5px 8px;
            text-align: left;
            border: 1px solid #c8e6c9;
        }

        .salary-table td {
            padding: 5px 8px;
            border: 1px solid #e0e0e0;
            font-size: 11px;
        }

        .salary-table tr:nth-child(even) td {
            background-color: #f9fbe7;
        }

        .salary-table tfoot td {
            font-weight: bold;
            background-color: #e8f5e9;
            border-top: 2px solid #2d6a4f;
        }

        .text-right { text-align: right; }

        /* Badges statut/type */
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: bold;
        }

        .badge-active   { background: #d4edda; color: #155724; }
        .badge-cdi      { background: #cce5ff; color: #004085; }
        .badge-cdd      { background: #fff3cd; color: #856404; }
        .badge-seasonal { background: #fce8b2; color: #7b4c00; }
        .badge-intern   { background: #e2d9f3; color: #4a1d96; }

        /* Clauses */
        .clause {
            background: #f9f9f9;
            border-left: 3px solid #2d6a4f;
            padding: 8px 10px;
            font-size: 10px;
            color: #444;
            margin-bottom: 8px;
        }

        /* Signatures */
        .signatures {
            margin-top: 30px;
            width: 100%;
            border-collapse: collapse;
        }

        .signatures td {
            width: 50%;
            padding: 0 10px;
            vertical-align: top;
        }

        .sign-box {
            border: 1px solid #ccc;
            height: 70px;
            margin-top: 8px;
            border-radius: 3px;
        }

        .sign-label {
            font-size: 10px;
            font-weight: bold;
            color: #444;
        }

        .sign-name {
            font-size: 10px;
            color: #666;
            margin-top: 3px;
        }

        /* Pied de page */
        .footer {
            position: fixed;
            bottom: 15px;
            left: 40px;
            right: 40px;
            border-top: 1px solid #e0e0e0;
            padding-top: 5px;
            font-size: 9px;
            color: #aaa;
            text-align: center;
        }
    </style>
</head>
<body>
<div class="page">

    {{-- En-tête --}}
    <div class="header">
        <div>
            <div class="org-name">{{ $organisation->name }}</div>
            @if($organisation->address)
                <div class="org-meta">{{ $organisation->address }}</div>
            @endif
            @if($organisation->phone ?? null)
                <div class="org-meta">Tél. : {{ $organisation->phone }}</div>
            @endif
        </div>
        <div class="doc-title">
            <h1>Contrat de travail</h1>
            <div class="contract-number">N° {{ $contract->contract_number }}</div>
            <div class="contract-number">Émis le {{ now()->format('d/m/Y') }}</div>
        </div>
    </div>

    {{-- Parties --}}
    <div class="section">
        <div class="section-title">Parties au contrat</div>
        <table class="grid-2">
            <tr>
                <td>
                    <span class="label">Employeur</span>
                    <span class="value">{{ $organisation->name }}</span>
                </td>
                <td>
                    <span class="label">Employé</span>
                    <span class="value">{{ $employee->full_name }}</span>
                </td>
            </tr>
            <tr>
                <td>
                    <span class="label">Matricule</span>
                    <span class="value">{{ $employee->employee_number }}</span>
                </td>
                <td>
                    <span class="label">Date de naissance</span>
                    <span class="value">{{ $employee->birth_date?->format('d/m/Y') ?? '—' }}</span>
                </td>
            </tr>
            @if($employee->id_number)
            <tr>
                <td colspan="2">
                    <span class="label">Pièce d'identité</span>
                    <span class="value">{{ $employee->id_number }}</span>
                </td>
            </tr>
            @endif
        </table>
    </div>

    {{-- Conditions générales --}}
    <div class="section">
        <div class="section-title">Conditions du contrat</div>
        <table class="grid-2">
            <tr>
                <td>
                    <span class="label">Type de contrat</span>
                    <span class="value">
                        <span class="badge badge-{{ strtolower($contract->type->value) }}">
                            {{ $contract->type->getLabel() }}
                        </span>
                    </span>
                </td>
                <td>
                    <span class="label">Statut</span>
                    <span class="value">
                        <span class="badge badge-active">{{ $contract->status->getLabel() }}</span>
                    </span>
                </td>
            </tr>
            <tr>
                <td>
                    <span class="label">Date de début</span>
                    <span class="value">{{ $contract->start_date->format('d/m/Y') }}</span>
                </td>
                <td>
                    <span class="label">Date de fin</span>
                    <span class="value">{{ $contract->end_date?->format('d/m/Y') ?? 'Indéterminée (CDI)' }}</span>
                </td>
            </tr>
            @if($contract->position)
            <tr>
                <td>
                    <span class="label">Poste</span>
                    <span class="value">{{ $contract->position }}</span>
                </td>
                <td>
                    <span class="label">Lieu de travail</span>
                    <span class="value">{{ $contract->work_location ?? '—' }}</span>
                </td>
            </tr>
            @endif
            <tr>
                <td>
                    <span class="label">Heures / semaine</span>
                    <span class="value">{{ $contract->working_hours_per_week ?? 40 }} h</span>
                </td>
                <td>
                    <span class="label">Congés annuels</span>
                    <span class="value">{{ $contract->annual_leave_days ?? 24 }} jours</span>
                </td>
            </tr>
        </table>
    </div>

    {{-- Rémunération --}}
    <div class="section">
        <div class="section-title">Rémunération mensuelle</div>
        <table class="salary-table">
            <thead>
                <tr>
                    <th>Élément</th>
                    <th class="text-right">Montant (FCFA)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Salaire de base</td>
                    <td class="text-right">{{ number_format($contract->base_salary, 0, ',', ' ') }}</td>
                </tr>
                @if($contract->transport_allowance > 0)
                <tr>
                    <td>Indemnité de transport</td>
                    <td class="text-right">{{ number_format($contract->transport_allowance, 0, ',', ' ') }}</td>
                </tr>
                @endif
                @if($contract->housing_allowance > 0)
                <tr>
                    <td>Indemnité de logement</td>
                    <td class="text-right">{{ number_format($contract->housing_allowance, 0, ',', ' ') }}</td>
                </tr>
                @endif
                @if($contract->food_allowance > 0)
                <tr>
                    <td>Indemnité de repas</td>
                    <td class="text-right">{{ number_format($contract->food_allowance, 0, ',', ' ') }}</td>
                </tr>
                @endif
            </tbody>
            <tfoot>
                <tr>
                    <td>Salaire brut total</td>
                    <td class="text-right">{{ number_format($grossSalary, 0, ',', ' ') }}</td>
                </tr>
                <tr>
                    <td>Salaire net estimé <small style="font-weight:normal;color:#666">(après CNPS {{ number_format($cnps, 0, ',', ' ') }} + ITS {{ number_format($its, 0, ',', ' ') }})</small></td>
                    <td class="text-right">{{ number_format($netSalary, 0, ',', ' ') }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    {{-- Clauses / Notes --}}
    @if($contract->description || $contract->notes)
    <div class="section">
        <div class="section-title">Clauses particulières</div>
        @if($contract->description)
            <div class="clause">{{ $contract->description }}</div>
        @endif
        @if($contract->notes)
            <div class="clause">{{ $contract->notes }}</div>
        @endif
    </div>
    @endif

    {{-- Signatures --}}
    <div class="section" style="margin-top: 20px;">
        <div class="section-title">Signatures</div>
        <table class="signatures">
            <tr>
                <td>
                    <div class="sign-label">L'Employeur</div>
                    <div class="sign-name">{{ $organisation->name }}</div>
                    <div class="sign-box"></div>
                </td>
                <td>
                    <div class="sign-label">L'Employé</div>
                    <div class="sign-name">{{ $employee->full_name }}</div>
                    <div class="sign-box"></div>
                </td>
            </tr>
        </table>
        <p style="font-size:9px;color:#888;margin-top:8px;text-align:center;">
            Fait en deux exemplaires originaux, à _____________________, le _____ / _____ / _______
        </p>
    </div>

</div>

<div class="footer">
    {{ $organisation->name }} — Contrat N° {{ $contract->contract_number }} — Généré le {{ now()->format('d/m/Y à H:i') }}
</div>
</body>
</html>
