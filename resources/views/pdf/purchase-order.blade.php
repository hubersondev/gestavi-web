@php
    /** @var \App\Models\PurchaseOrder $order */
    $org = $order->organisation;
    $supplier = $order->supplier;
    $fmt = fn ($amount) => number_format((float) $amount, 0, ',', ' ').' XOF';
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Bon de commande {{ $order->order_number }}</title>
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
                        <h1>Bon de commande</h1>
                        <div class="doc-number">N° {{ $order->order_number }}</div>
                        <div class="doc-number">Date : {{ $order->order_date?->format('d/m/Y') }}</div>
                        @if ($order->expected_delivery_date)
                            <div class="doc-number">Livraison prévue : {{ $order->expected_delivery_date->format('d/m/Y') }}</div>
                        @endif
                    </td>
                </tr>
            </table>
        </div>

        <table class="parties">
            <tr>
                <td>
                    <div class="label">Fournisseur</div>
                    <div class="value">{{ $supplier?->name ?? '—' }}</div>
                    @if ($supplier?->phone)
                        <div>Tél : {{ $supplier->phone }}</div>
                    @endif
                    @if ($supplier?->email)
                        <div>Email : {{ $supplier->email }}</div>
                    @endif
                    @if ($supplier?->address)
                        <div>{{ $supplier->address }}</div>
                    @endif
                </td>
                <td>
                    <div class="label">Livraison</div>
                    @if ($order->delivery_address)
                        <div>{{ $order->delivery_address }}</div>
                    @elseif ($order->farm)
                        <div class="value">{{ $order->farm->name }}</div>
                        @if ($order->farm->address)
                            <div>{{ $order->farm->address }}</div>
                        @endif
                    @else
                        <div>—</div>
                    @endif
                    @if ($order->delivery_contact)
                        <div style="margin-top: 5px;">Contact : {{ $order->delivery_contact }}</div>
                    @endif
                    @if ($order->delivery_phone)
                        <div>Tél : {{ $order->delivery_phone }}</div>
                    @endif
                </td>
            </tr>
            <tr>
                <td>
                    <div class="label">Statut</div>
                    <div class="value">{{ $order->status->getLabel() }}</div>
                </td>
                <td>
                    <div class="label">Conditions de paiement</div>
                    <div class="value">{{ $order->payment_terms?->getLabel() ?? '—' }}</div>
                    @if ($order->payment_terms_details)
                        <div style="margin-top: 3px;">{{ $order->payment_terms_details }}</div>
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
                @foreach ($order->items as $item)
                    <tr>
                        <td>
                            <strong>{{ $item->product_name }}</strong>
                            @if ($item->description)
                                <div style="font-size: 9px; color: #666; margin-top: 2px;">{{ $item->description }}</div>
                            @endif
                        </td>
                        <td class="text-center">{{ rtrim(rtrim(number_format((float) $item->quantity_ordered, 2, ',', ' '), '0'), ',') }}</td>
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
                <td class="value">{{ $fmt($order->subtotal) }}</td>
            </tr>
            @if ((float) $order->discount_amount > 0)
                <tr>
                    <td class="label">Remise</td>
                    <td class="value">- {{ $fmt($order->discount_amount) }}</td>
                </tr>
            @endif
            @if ((float) $order->shipping_cost > 0)
                <tr>
                    <td class="label">Frais de livraison</td>
                    <td class="value">{{ $fmt($order->shipping_cost) }}</td>
                </tr>
            @endif
            @if ((float) $order->tax_amount > 0)
                <tr>
                    <td class="label">TVA</td>
                    <td class="value">{{ $fmt($order->tax_amount) }}</td>
                </tr>
            @endif
            <tr class="total-row">
                <td class="label">TOTAL</td>
                <td class="value">{{ $fmt($order->total) }}</td>
            </tr>
        </table>

        @if ($order->notes)
            <div class="notes">
                <div class="section-title">Notes</div>
                {!! nl2br(e($order->notes)) !!}
            </div>
        @endif

        <table class="signature-block">
            <tr>
                <td>
                    <div class="sig-line">Acheteur : {{ $order->creator?->name ?? '—' }}</div>
                </td>
                <td>
                    <div class="sig-line">Fournisseur : signature et cachet</div>
                </td>
            </tr>
        </table>

        <div class="footer">
            Document généré le {{ now()->format('d/m/Y H:i') }} — {{ $org?->name ?? 'Organisation' }}
        </div>
    </div>
</body>
</html>
