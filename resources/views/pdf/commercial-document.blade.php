@php
    /** @var 'sale'|'quote' $type */
    /** @var \App\Models\Sale|\App\Models\Quote $document */
    $isSale = $type === 'sale';
    $orgName = $document->organisation?->name ?? 'Organisation';
    $orgEmail = $document->organisation?->email;
    $orgPhone = $document->organisation?->phone;
    $orgAddress = $document->organisation?->address;

    $customerName = $document->customer?->name
        ?? ($isSale ? null : $document->client_name)
        ?? 'Client comptant';
    $customerPhone = $document->customer?->phone ?? (! $isSale ? $document->client_phone : null);
    $customerEmail = $document->customer?->email ?? (! $isSale ? $document->client_email : null);
    $customerAddress = $document->customer?->address ?? (! $isSale ? $document->client_address : null);

    $docTitle = $isSale ? 'FACTURE' : 'DEVIS';
    $docNumber = $isSale ? $document->invoice_number : $document->quote_number;
    $docDate = $isSale ? $document->sale_date : $document->quote_date;
    $secondaryDateLabel = $isSale ? 'Échéance' : 'Valide jusqu\'au';
    $secondaryDate = $isSale ? $document->due_date : $document->valid_until;

    $fmt = fn ($amount) => number_format((float) $amount, 0, ',', ' ').' XOF';
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{ $docTitle }} {{ $docNumber }}</title>
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
            width: 100%;
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

        .parties {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        .parties td {
            vertical-align: top;
            padding: 10px;
            width: 50%;
            border: 1px solid #e0e0e0;
        }
        .parties .label {
            font-size: 9px;
            text-transform: uppercase;
            color: #666;
            letter-spacing: 0.5px;
            margin-bottom: 3px;
        }
        .parties .value { font-size: 11px; font-weight: bold; }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        .items-table thead th {
            background: #2d6a4f;
            color: white;
            padding: 8px 6px;
            text-align: left;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .items-table tbody td {
            padding: 6px;
            border-bottom: 1px solid #e0e0e0;
            font-size: 10px;
        }
        .items-table .text-right { text-align: right; }
        .items-table .text-center { text-align: center; }

        .totals {
            width: 50%;
            margin-left: 50%;
            margin-bottom: 20px;
            border-collapse: collapse;
        }
        .totals td {
            padding: 5px 8px;
            font-size: 11px;
            border-bottom: 1px solid #f0f0f0;
        }
        .totals .label { text-align: right; color: #555; }
        .totals .value { text-align: right; font-weight: bold; }
        .totals .total-row td {
            border-top: 2px solid #2d6a4f;
            background: #f0f7f3;
            font-size: 13px;
            color: #2d6a4f;
            padding: 8px;
        }

        .notes, .terms {
            margin-bottom: 14px;
            padding: 10px;
            background: #fafafa;
            border-left: 3px solid #2d6a4f;
            font-size: 10px;
        }
        .notes .section-title, .terms .section-title {
            font-weight: bold;
            color: #2d6a4f;
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

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="header">
            <table class="header-table">
                <tr>
                    <td class="header-left">
                        <div class="org-name">{{ $orgName }}</div>
                        @if ($orgAddress)
                            <div class="org-meta">{{ $orgAddress }}</div>
                        @endif
                        @if ($orgPhone || $orgEmail)
                            <div class="org-meta">
                                {{ $orgPhone }}{{ $orgPhone && $orgEmail ? ' — ' : '' }}{{ $orgEmail }}
                            </div>
                        @endif
                    </td>
                    <td class="header-right doc-title">
                        <h1>{{ $docTitle }}</h1>
                        <div class="doc-number">N° {{ $docNumber }}</div>
                        <div class="doc-number">Date : {{ optional($docDate)->format('d/m/Y') }}</div>
                        @if ($secondaryDate)
                            <div class="doc-number">{{ $secondaryDateLabel }} : {{ $secondaryDate->format('d/m/Y') }}</div>
                        @endif
                    </td>
                </tr>
            </table>
        </div>

        <table class="parties">
            <tr>
                <td>
                    <div class="label">Client</div>
                    <div class="value">{{ $customerName }}</div>
                    @if ($customerPhone)
                        <div>Tél : {{ $customerPhone }}</div>
                    @endif
                    @if ($customerEmail)
                        <div>Email : {{ $customerEmail }}</div>
                    @endif
                    @if ($customerAddress)
                        <div>{{ $customerAddress }}</div>
                    @endif
                </td>
                <td>
                    <div class="label">Statut</div>
                    <div class="value">{{ $document->status->getLabel() }}</div>
                    @if ($isSale && $document->seller)
                        <div style="margin-top: 5px;"><span class="label">Vendeur :</span> {{ $document->seller->name }}</div>
                    @endif
                </td>
            </tr>
        </table>

        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 45%;">Désignation</th>
                    <th class="text-center" style="width: 10%;">Qté</th>
                    <th style="width: 10%;">Unité</th>
                    <th class="text-right" style="width: 15%;">Prix unitaire</th>
                    <th class="text-center" style="width: 8%;">Remise</th>
                    <th class="text-right" style="width: 12%;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($document->items as $item)
                    <tr>
                        <td>
                            <strong>{{ $item->product_name }}</strong>
                            @if ($item->description)
                                <div style="font-size: 9px; color: #666; margin-top: 2px;">{{ $item->description }}</div>
                            @endif
                        </td>
                        <td class="text-center">{{ rtrim(rtrim(number_format((float) $item->quantity, 2, ',', ' '), '0'), ',') }}</td>
                        <td>{{ $item->unit }}</td>
                        <td class="text-right">{{ $fmt($item->unit_price) }}</td>
                        <td class="text-center">{{ (float) $item->discount_percent > 0 ? rtrim(rtrim(number_format((float) $item->discount_percent, 2, ',', ' '), '0'), ',').' %' : '—' }}</td>
                        <td class="text-right"><strong>{{ $fmt($item->total) }}</strong></td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="totals">
            <tr>
                <td class="label">Sous-total</td>
                <td class="value">{{ $fmt($document->subtotal) }}</td>
            </tr>
            @if ((float) $document->discount_amount > 0)
                <tr>
                    <td class="label">Remise globale</td>
                    <td class="value">- {{ $fmt($document->discount_amount) }}</td>
                </tr>
            @endif
            @if ((float) $document->tax_amount > 0)
                <tr>
                    <td class="label">TVA ({{ rtrim(rtrim(number_format((float) $document->tax_percent, 2, ',', ' '), '0'), ',') }} %)</td>
                    <td class="value">{{ $fmt($document->tax_amount) }}</td>
                </tr>
            @endif
            <tr class="total-row">
                <td class="label">TOTAL</td>
                <td class="value">{{ $fmt($document->total) }}</td>
            </tr>
            @if ($isSale)
                <tr>
                    <td class="label">Payé</td>
                    <td class="value" style="color: #2d6a4f;">{{ $fmt($document->paid) }}</td>
                </tr>
                <tr>
                    <td class="label">Solde dû</td>
                    <td class="value" style="color: {{ (float) $document->balance > 0 ? '#c0392b' : '#2d6a4f' }};">
                        {{ $fmt($document->balance) }}
                    </td>
                </tr>
            @endif
        </table>

        @if (! $isSale && $document->terms_conditions)
            <div class="terms">
                <div class="section-title">Conditions générales</div>
                {!! nl2br(e($document->terms_conditions)) !!}
            </div>
        @endif

        @if ($document->notes)
            <div class="notes">
                <div class="section-title">Notes</div>
                {!! nl2br(e($document->notes)) !!}
            </div>
        @endif

        <div class="footer">
            Document généré le {{ now()->format('d/m/Y H:i') }} —
            {{ $orgName }}
            @if (! $isSale)
                — Ce devis n'a pas de valeur contractuelle tant qu'il n'est pas accepté et signé.
            @endif
        </div>
    </div>
</body>
</html>
