@php
    /** @var \App\Models\Expense $expense */
    $org = $expense->organisation;
    $fmt = fn ($amount) => number_format((float) $amount, 0, ',', ' ').' XOF';
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Dépense {{ $expense->expense_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #1a1a1a;
            line-height: 1.5;
        }

        .page { padding: 30px 40px; }

        .header {
            border-bottom: 2px solid #c0392b;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }

        .header-table { width: 100%; border-collapse: collapse; }
        .header-table td { vertical-align: top; padding: 0; }
        .header-left { width: 60%; }
        .header-right { width: 40%; text-align: right; }

        .org-name { font-size: 16px; font-weight: bold; color: #c0392b; }
        .org-meta { font-size: 10px; color: #555; margin-top: 3px; }

        .doc-title h1 {
            font-size: 16px;
            font-weight: bold;
            color: #c0392b;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .doc-title .doc-number { font-size: 11px; color: #666; margin-top: 3px; }

        .section {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        .section td {
            vertical-align: top;
            padding: 8px 10px;
            border: 1px solid #e0e0e0;
            width: 50%;
        }
        .section .label {
            font-size: 9px;
            text-transform: uppercase;
            color: #666;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }
        .section .value {
            font-size: 11px;
            font-weight: bold;
        }

        .amounts {
            width: 60%;
            margin-left: 40%;
            margin-bottom: 20px;
            border-collapse: collapse;
        }
        .amounts td {
            padding: 6px 10px;
            font-size: 11px;
            border-bottom: 1px solid #f0f0f0;
        }
        .amounts .label { text-align: right; color: #555; }
        .amounts .value { text-align: right; font-weight: bold; }
        .amounts .total-row td {
            border-top: 2px solid #c0392b;
            background: #fdecea;
            font-size: 13px;
            color: #c0392b;
            padding: 8px 10px;
        }

        .description-box {
            padding: 10px;
            background: #fafafa;
            border-left: 3px solid #c0392b;
            font-size: 11px;
            margin-bottom: 14px;
        }
        .description-box .section-title {
            font-weight: bold;
            color: #c0392b;
            text-transform: uppercase;
            font-size: 10px;
            margin-bottom: 5px;
        }

        .notes {
            padding: 10px;
            background: #fafafa;
            border-left: 3px solid #888;
            font-size: 10px;
            margin-bottom: 14px;
        }
        .notes .section-title {
            font-weight: bold;
            color: #555;
            text-transform: uppercase;
            font-size: 10px;
            margin-bottom: 5px;
        }

        .footer {
            margin-top: 25px;
            padding-top: 10px;
            border-top: 1px solid #e0e0e0;
            text-align: center;
            font-size: 9px;
            color: #888;
        }

        .signature-block {
            margin-top: 40px;
            width: 100%;
            border-collapse: collapse;
        }
        .signature-block td {
            width: 50%;
            padding: 20px 10px 5px;
            text-align: center;
            vertical-align: bottom;
        }
        .signature-block .sig-line {
            border-top: 1px solid #333;
            padding-top: 4px;
            font-size: 10px;
            color: #555;
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="header">
            <table class="header-table">
                <tr>
                    <td class="header-left">
                        <div class="org-name">{{ $org?->name ?? 'Organisation' }}</div>
                        @if ($org?->address)
                            <div class="org-meta">{{ $org->address }}</div>
                        @endif
                        @if ($org?->phone || $org?->email)
                            <div class="org-meta">
                                {{ $org?->phone }}{{ $org?->phone && $org?->email ? ' — ' : '' }}{{ $org?->email }}
                            </div>
                        @endif
                    </td>
                    <td class="header-right doc-title">
                        <h1>Reçu de dépense</h1>
                        <div class="doc-number">N° {{ $expense->expense_number }}</div>
                        <div class="doc-number">Date : {{ $expense->expense_date?->format('d/m/Y') }}</div>
                        @if ($expense->due_date)
                            <div class="doc-number">Échéance : {{ $expense->due_date->format('d/m/Y') }}</div>
                        @endif
                    </td>
                </tr>
            </table>
        </div>

        <table class="section">
            <tr>
                <td>
                    <div class="label">Catégorie</div>
                    <div class="value">{{ $expense->category->getLabel() }}</div>
                </td>
                <td>
                    <div class="label">Statut</div>
                    <div class="value">{{ $expense->status->getLabel() }}</div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="label">Fournisseur</div>
                    <div class="value">{{ $expense->supplier?->name ?? '—' }}</div>
                    @if ($expense->reference)
                        <div style="margin-top: 3px; font-size: 10px;">Facture : {{ $expense->reference }}</div>
                    @endif
                </td>
                <td>
                    <div class="label">Ferme / Bande</div>
                    <div class="value">{{ $expense->farm?->name ?? 'Organisation' }}</div>
                    @if ($expense->flock)
                        <div style="margin-top: 3px; font-size: 10px;">Bande : {{ $expense->flock->code }} — {{ $expense->flock->name }}</div>
                    @endif
                </td>
            </tr>
        </table>

        <div class="description-box">
            <div class="section-title">Description</div>
            {!! nl2br(e($expense->description)) !!}
        </div>

        <table class="amounts">
            <tr>
                <td class="label">Montant de la dépense</td>
                <td class="value">{{ $fmt($expense->amount) }}</td>
            </tr>
            <tr>
                <td class="label">Payé</td>
                <td class="value" style="color: #2d6a4f;">{{ $fmt($expense->paid_amount) }}</td>
            </tr>
            <tr class="total-row">
                <td class="label">Solde dû</td>
                <td class="value">{{ $fmt($expense->balance) }}</td>
            </tr>
        </table>

        @if ($expense->account)
            <div class="description-box" style="border-left-color: #3498db;">
                <div class="section-title" style="color: #3498db;">Compte débité</div>
                {{ $expense->account->name }} ({{ $expense->account->type->getLabel() }})
                @if ($expense->paid_at)
                    <div style="margin-top: 3px; color: #666; font-size: 10px;">
                        Paiement complet enregistré le {{ $expense->paid_at->format('d/m/Y H:i') }}
                    </div>
                @endif
            </div>
        @endif

        @if ($expense->notes)
            <div class="notes">
                <div class="section-title">Notes internes</div>
                {!! nl2br(e($expense->notes)) !!}
            </div>
        @endif

        <table class="signature-block">
            <tr>
                <td>
                    <div class="sig-line">Émetteur : {{ $expense->recorder?->name ?? '—' }}</div>
                </td>
                <td>
                    <div class="sig-line">Visa / validation</div>
                </td>
            </tr>
        </table>

        <div class="footer">
            Document généré le {{ now()->format('d/m/Y H:i') }} — {{ $org?->name ?? 'Organisation' }}
        </div>
    </div>
</body>
</html>
