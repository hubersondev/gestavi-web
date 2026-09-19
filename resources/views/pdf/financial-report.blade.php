@php
    /** @var \App\Models\Organisation $organisation */
    /** @var array $kpis */
    /** @var \Illuminate\Support\Collection $transactions */
    /** @var \Illuminate\Support\Collection $expensesByCategory */
    $fmt = fn ($v) => number_format((float) $v, 0, ',', ' ').' XOF';
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Rapport Financier</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1a1a1a; line-height: 1.4; }
        .page { padding: 20px 25px; }

        .header { border-bottom: 2px solid #1a56db; padding-bottom: 8px; margin-bottom: 12px; }
        .header-table { width: 100%; border-collapse: collapse; }
        .header-table td { vertical-align: top; }
        .org-name { font-size: 14px; font-weight: bold; color: #1a56db; }
        .org-meta { font-size: 8px; color: #555; }
        .doc-title h1 { font-size: 13px; color: #1a56db; text-align: right; text-transform: uppercase; }
        .doc-title .meta { font-size: 9px; color: #666; text-align: right; }

        .kpi-grid { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .kpi-grid td {
            width: 25%; text-align: center; padding: 8px 4px;
            border: 1px solid #e0e0e0; background: #eff6ff;
        }
        .kpi-grid .value { font-size: 14px; font-weight: bold; color: #1a56db; }
        .kpi-grid .label { font-size: 8px; color: #555; text-transform: uppercase; }
        .kpi-grid .value.success { color: #2d6a4f; }
        .kpi-grid .value.danger { color: #c0392b; }

        .section-title { font-size: 10px; font-weight: bold; color: #1a56db; border-bottom: 1px solid #dbeafe; padding-bottom: 3px; margin: 10px 0 6px; text-transform: uppercase; }

        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .data-table thead th {
            background: #1a56db; color: white; padding: 5px 3px;
            font-size: 7px; text-transform: uppercase; text-align: center;
        }
        .data-table tbody td {
            padding: 3px; border-bottom: 1px solid #eee; font-size: 8px; text-align: center;
        }
        .data-table tbody tr:nth-child(even) td { background: #f9fafb; }
        .text-right { text-align: right !important; }
        .text-left { text-align: left !important; }
        .text-success { color: #2d6a4f; }
        .text-danger { color: #c0392b; }
        .bold { font-weight: bold; }

        .footer { margin-top: 10px; text-align: center; font-size: 7px; color: #888; border-top: 1px solid #e0e0e0; padding-top: 5px; }
    </style>
</head>
<body>
    <div class="page">
        <div class="header">
            <table class="header-table">
                <tr>
                    <td style="width: 60%;">
                        <div class="org-name">{{ $organisation?->name ?? 'Organisation' }}</div>
                        @if ($organisation?->address)<div class="org-meta">{{ $organisation->address }}</div>@endif
                    </td>
                    <td style="width: 40%;" class="doc-title">
                        <h1>Rapport Financier</h1>
                        <div class="meta">Du {{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }} au {{ \Carbon\Carbon::parse($dateTo)->format('d/m/Y') }}</div>
                    </td>
                </tr>
            </table>
        </div>

        <table class="kpi-grid">
            <tr>
                <td><div class="value success">{{ $fmt($kpis['credits']) }}</div><div class="label">Entrées</div></td>
                <td><div class="value danger">{{ $fmt($kpis['debits']) }}</div><div class="label">Sorties</div></td>
                <td><div class="value {{ $kpis['net'] >= 0 ? 'success' : 'danger' }}">{{ $fmt($kpis['net']) }}</div><div class="label">Solde net</div></td>
                <td><div class="value">{{ $kpis['count'] }}</div><div class="label">Transactions</div></td>
            </tr>
        </table>

        @if ($expensesByCategory->isNotEmpty())
            <div class="section-title">Dépenses par catégorie</div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="text-left">Catégorie</th>
                        <th>Nombre</th>
                        <th class="text-right">Montant (XOF)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($expensesByCategory as $expense)
                        <tr>
                            <td class="text-left bold">{{ \App\Enums\ExpenseCategory::tryFrom($expense->category)?->getLabel() ?? $expense->category }}</td>
                            <td>{{ $expense->count }}</td>
                            <td class="text-right bold text-danger">{{ number_format($expense->total, 0, ',', ' ') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <div class="section-title">Détail des transactions</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th class="text-left">Date</th>
                    <th class="text-left">Compte</th>
                    <th>Type</th>
                    <th class="text-left">Catégorie</th>
                    <th class="text-right">Montant</th>
                    <th class="text-left">Description</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($transactions as $tx)
                    <tr>
                        <td class="text-left">{{ $tx->transaction_date?->format('d/m') }}</td>
                        <td class="text-left">{{ $tx->account?->name }}</td>
                        <td class="{{ $tx->isCredit() ? 'text-success' : 'text-danger' }} bold">{{ $tx->type?->getLabel() }}</td>
                        <td class="text-left">{{ $tx->category?->getLabel() }}</td>
                        <td class="text-right bold {{ $tx->isCredit() ? 'text-success' : 'text-danger' }}">{{ number_format($tx->amount, 0, ',', ' ') }}</td>
                        <td class="text-left" style="max-width: 150px; overflow: hidden;">{{ \Illuminate\Support\Str::limit($tx->description, 40) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="footer">
            Rapport généré le {{ now()->format('d/m/Y H:i') }} — {{ $organisation?->name }}
        </div>
    </div>
</body>
</html>
