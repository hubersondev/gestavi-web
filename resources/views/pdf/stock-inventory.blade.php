@php
    /** @var \App\Models\StockInventory $inventory */
    $org = $inventory->organisation;
    $stock = $inventory->stock;
    $diff = (float) $inventory->difference;
    $fmt = fn ($amount) => number_format((float) $amount, 0, ',', ' ').' XOF';
    $qty = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, ',', ' '), '0'), ',');
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Inventaire {{ $inventory->inventory_ref }}</title>
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
            border-bottom: 2px solid #2d6a4f;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .header-table { width: 100%; border-collapse: collapse; }
        .header-table td { vertical-align: top; padding: 0; }
        .header-left { width: 60%; }
        .header-right { width: 40%; text-align: right; }

        .org-name { font-size: 16px; font-weight: bold; color: #2d6a4f; }
        .org-meta { font-size: 10px; color: #555; margin-top: 3px; }

        .doc-title h1 {
            font-size: 16px;
            font-weight: bold;
            color: #2d6a4f;
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
            padding: 10px;
            border: 1px solid #e0e0e0;
        }
        .section .label {
            font-size: 9px;
            text-transform: uppercase;
            color: #666;
            letter-spacing: 0.5px;
            margin-bottom: 3px;
        }
        .section .value { font-size: 11px; font-weight: bold; }

        .quantities {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .quantities th {
            background: #2d6a4f;
            color: white;
            padding: 10px 8px;
            text-align: center;
            font-size: 10px;
            text-transform: uppercase;
            width: 33.33%;
        }
        .quantities td {
            padding: 14px 8px;
            text-align: center;
            font-size: 16px;
            font-weight: bold;
            border: 1px solid #e0e0e0;
        }

        .variance {
            padding: 16px;
            margin-bottom: 20px;
            border-left: 5px solid;
            background: #fafafa;
        }
        .variance-positive { border-left-color: #2d6a4f; }
        .variance-negative { border-left-color: #c0392b; }
        .variance-none { border-left-color: #888; }
        .variance .title {
            font-size: 10px;
            text-transform: uppercase;
            color: #666;
            margin-bottom: 5px;
        }
        .variance .big {
            font-size: 20px;
            font-weight: bold;
        }
        .variance-positive .big { color: #2d6a4f; }
        .variance-negative .big { color: #c0392b; }

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

        .footer {
            margin-top: 25px;
            padding-top: 10px;
            border-top: 1px solid #e0e0e0;
            text-align: center;
            font-size: 9px;
            color: #888;
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
                        <h1>Rapport d'inventaire</h1>
                        <div class="doc-number">N° {{ $inventory->inventory_ref }}</div>
                        <div class="doc-number">Date : {{ $inventory->inventory_date?->format('d/m/Y') }}</div>
                        <div class="doc-number">Statut : {{ $inventory->status->getLabel() }}</div>
                    </td>
                </tr>
            </table>
        </div>

        <table class="section">
            <tr>
                <td style="width: 50%;">
                    <div class="label">Produit inventorié</div>
                    <div class="value">{{ $stock?->name ?? '—' }}</div>
                    @if ($stock?->code)
                        <div>Code : {{ $stock->code }}</div>
                    @endif
                    @if ($stock?->category)
                        <div>Catégorie : {{ $stock->category->name }}</div>
                    @endif
                    @if ($stock?->location)
                        <div>Emplacement : {{ $stock->location }}</div>
                    @endif
                </td>
                <td style="width: 50%;">
                    <div class="label">Lot inventorié</div>
                    @if ($inventory->stockLot)
                        <div class="value">{{ $inventory->stockLot->lot_number }}</div>
                        @if ($inventory->stockLot->expiry_date)
                            <div>Périme le : {{ $inventory->stockLot->expiry_date->format('d/m/Y') }}</div>
                        @endif
                    @else
                        <div>—</div>
                    @endif
                </td>
            </tr>
        </table>

        <table class="quantities">
            <thead>
                <tr>
                    <th>Quantité théorique</th>
                    <th>Quantité physique</th>
                    <th>Écart</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ $qty($inventory->theoretical_quantity) }} {{ $stock?->unit }}</td>
                    <td>{{ $qty($inventory->physical_quantity) }} {{ $stock?->unit }}</td>
                    <td>{{ $diff > 0 ? '+' : '' }}{{ $qty($diff) }} {{ $stock?->unit }}</td>
                </tr>
            </tbody>
        </table>

        <div class="variance {{ $diff > 0 ? 'variance-positive' : ($diff < 0 ? 'variance-negative' : 'variance-none') }}">
            <div class="title">Valorisation de l'écart</div>
            <div class="big">
                @if ($diff > 0)
                    + {{ $fmt($inventory->difference_value) }}
                @elseif ($diff < 0)
                    {{ $fmt($inventory->difference_value) }}
                @else
                    Aucun écart
                @endif
            </div>
            @if ($inventory->difference_percent !== null)
                <div style="font-size: 11px; color: #555; margin-top: 4px;">
                    Écart relatif : {{ $diff > 0 ? '+' : '' }}{{ number_format((float) $inventory->difference_percent, 2, ',', ' ') }} %
                </div>
            @endif
        </div>

        @if ($inventory->difference_reason || $inventory->justification)
            <div class="notes" style="border-left-color: #c0392b;">
                <div class="section-title" style="color: #c0392b;">Motif de l'écart</div>
                @if ($inventory->difference_reason)
                    <div style="font-weight: bold; margin-bottom: 4px;">{{ $inventory->difference_reason->getLabel() }}</div>
                @endif
                @if ($inventory->justification)
                    <div>{!! nl2br(e($inventory->justification)) !!}</div>
                @endif
            </div>
        @endif

        @if ($inventory->notes)
            <div class="notes">
                <div class="section-title">Notes internes</div>
                {!! nl2br(e($inventory->notes)) !!}
            </div>
        @endif

        <table class="signature-block">
            <tr>
                <td>
                    <div class="sig-line">Effectué par : {{ $inventory->conductor?->name ?? '—' }}</div>
                </td>
                <td>
                    <div class="sig-line">Validé par : {{ $inventory->validator?->name ?? '—' }}</div>
                </td>
            </tr>
        </table>

        <div class="footer">
            Document généré le {{ now()->format('d/m/Y H:i') }} — {{ $org?->name ?? 'Organisation' }}
        </div>
    </div>
</body>
</html>
