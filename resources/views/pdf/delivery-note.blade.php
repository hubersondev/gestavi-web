@php
    /** @var \App\Models\Delivery $delivery */
    $sale = $delivery->sale;
    $org = $sale?->organisation;
    $customerName = $sale?->customer?->name ?? $delivery->recipient_name ?? 'Client comptant';
    $fmt = fn ($value, int $decimals = 0) => number_format((float) $value, $decimals, ',', ' ');
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Bon de livraison {{ $delivery->delivery_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1a1a; line-height: 1.5; }
        .page { padding: 30px 40px; }
        .header { border-bottom: 2px solid #2d6a4f; padding-bottom: 12px; margin-bottom: 20px; }
        .header-table, .parties, .items-table, .signatures { width: 100%; border-collapse: collapse; }
        .header-table td { vertical-align: top; }
        .org-name { font-size: 16px; font-weight: bold; color: #2d6a4f; }
        .org-meta { font-size: 10px; color: #555; }
        .doc-title { text-align: right; }
        .doc-title h1 { font-size: 16px; color: #2d6a4f; }
        .parties td { width: 50%; vertical-align: top; padding: 10px; border: 1px solid #ddd; }
        .label { font-size: 9px; text-transform: uppercase; color: #777; letter-spacing: .5px; }
        .value { font-weight: bold; font-size: 12px; }
        .items-table { margin-top: 20px; }
        .items-table th { background: #2d6a4f; color: #fff; padding: 6px; text-align: left; font-size: 10px; }
        .items-table td { padding: 6px; border-bottom: 1px solid #e5e5e5; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .signatures { margin-top: 40px; }
        .signatures td { width: 50%; padding: 10px; border-top: 1px solid #999; vertical-align: top; height: 80px; }
        .footer { margin-top: 30px; font-size: 9px; color: #777; text-align: center; }
    </style>
</head>
<body>
<div class="page">
    <div class="header">
        <table class="header-table">
            <tr>
                <td style="width: 60%;">
                    <div class="org-name">{{ $org?->name ?? 'Organisation' }}</div>
                    @if ($org?->address)<div class="org-meta">{{ $org->address }}</div>@endif
                    @if ($org?->phone || $org?->email)
                        <div class="org-meta">{{ $org?->phone }}{{ $org?->phone && $org?->email ? ' — ' : '' }}{{ $org?->email }}</div>
                    @endif
                    @if ($delivery->farm)<div class="org-meta">Ferme : {{ $delivery->farm->name }}</div>@endif
                </td>
                <td class="doc-title">
                    <h1>BON DE LIVRAISON</h1>
                    <div>N° {{ $delivery->delivery_number }}</div>
                    <div>Date : {{ optional($delivery->delivery_date)->format('d/m/Y') }}</div>
                    @if ($sale)<div>Facture : {{ $sale->invoice_number }} du {{ optional($sale->sale_date)->format('d/m/Y') }}</div>@endif
                    <div>Statut : {{ $delivery->status->getLabel() }}</div>
                </td>
            </tr>
        </table>
    </div>

    <table class="parties">
        <tr>
            <td>
                <div class="label">Destinataire</div>
                <div class="value">{{ $customerName }}</div>
                @if ($delivery->recipient_phone)<div>Tél : {{ $delivery->recipient_phone }}</div>@endif
                @if ($delivery->delivery_address)<div>{{ $delivery->delivery_address }}</div>@endif
            </td>
            <td>
                <div class="label">Transport</div>
                <div>Livreur : {{ $delivery->driver_name ?? '—' }}</div>
                <div>Véhicule : {{ $delivery->vehicle ?? '—' }}</div>
                @if ($delivery->delivered_at)<div>Livrée le : {{ $delivery->delivered_at->format('d/m/Y H:i') }}</div>@endif
            </td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 55%;">Désignation</th>
                <th class="text-center" style="width: 15%;">Quantité</th>
                <th style="width: 15%;">Unité</th>
                <th style="width: 15%;">Bande</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($sale?->items ?? [] as $item)
                <tr>
                    <td>{{ $item->product_name }}@if ($item->description)<br><span class="org-meta">{{ $item->description }}</span>@endif</td>
                    <td class="text-center">{{ $fmt($item->quantity, 2) }}</td>
                    <td>{{ $item->unit }}</td>
                    <td>{{ $item->flock?->code ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-center">Aucune ligne</td></tr>
            @endforelse
        </tbody>
    </table>

    @if ($delivery->notes)
        <p style="margin-top: 15px;"><span class="label">Notes :</span> {!! nl2br(e($delivery->notes)) !!}</p>
    @endif

    <table class="signatures">
        <tr>
            <td>
                <div class="label">Livreur</div>
                <div>{{ $delivery->driver_name ?? '' }}</div>
            </td>
            <td>
                <div class="label">Réception (nom, date, signature)</div>
                <div>{{ $delivery->recipient_name ?? '' }}</div>
                @if ($delivery->signature_note)<div class="org-meta">{{ $delivery->signature_note }}</div>@endif
            </td>
        </tr>
    </table>

    <div class="footer">Document généré par GESTAVI le {{ now()->format('d/m/Y à H:i') }} — les quantités livrées doivent être vérifiées à la réception.</div>
</div>
</body>
</html>
