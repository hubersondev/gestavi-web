@php
    /** @var \App\Models\SubscriptionPayment $payment */
    $organisation = $payment->organisation;
    $settings = \App\Services\PlatformSettings::all();
    $fmt = fn ($amount) => number_format((float) $amount, 0, ',', ' ').' '.$payment->currency;
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Facture {{ $payment->invoice_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1a1a; line-height: 1.5; }
        .page { padding: 30px 40px; }
        .header { border-bottom: 2px solid #2d6a4f; padding-bottom: 12px; margin-bottom: 24px; }
        .header-table, .parties, .lines, .totals { width: 100%; border-collapse: collapse; }
        .header-table td, .parties td { vertical-align: top; padding: 0; }
        .org-name { font-size: 16px; font-weight: bold; color: #2d6a4f; }
        .meta { font-size: 10px; color: #555; margin-top: 3px; }
        .doc-title { text-align: right; }
        .doc-title h1 { font-size: 18px; color: #2d6a4f; }
        .doc-title .num { font-size: 12px; font-weight: bold; margin-top: 4px; }
        .parties { margin-bottom: 24px; }
        .parties td { width: 50%; padding-right: 20px; }
        .label { font-size: 9px; text-transform: uppercase; letter-spacing: .05em; color: #777; margin-bottom: 4px; }
        .lines th { background: #eef5f1; text-align: left; padding: 7px 8px; font-size: 10px; text-transform: uppercase; color: #2d6a4f; border-bottom: 1px solid #cfe0d6; }
        .lines td { padding: 8px; border-bottom: 1px solid #e5e5e5; }
        .right { text-align: right; }
        .totals { margin-top: 10px; }
        .totals td { padding: 5px 8px; }
        .totals .grand td { font-size: 13px; font-weight: bold; border-top: 2px solid #2d6a4f; }
        .payment { margin-top: 24px; padding: 10px 12px; background: #f6f8f7; border-left: 3px solid #2d6a4f; font-size: 10.5px; }
        .footer { margin-top: 36px; padding-top: 10px; border-top: 1px solid #ddd; font-size: 9px; color: #777; text-align: center; }
    </style>
</head>
<body>
<div class="page">
    <div class="header">
        <table class="header-table">
            <tr>
                <td style="width: 60%">
                    <div class="org-name">{{ $settings['company_name'] }}</div>
                    <div class="meta">
                        @if ($settings['company_address']) {{ $settings['company_address'] }}<br> @endif
                        @if ($settings['company_tax_id']) {{ $settings['company_tax_id'] }}<br> @endif
                        @if ($settings['company_email']) {{ $settings['company_email'] }} @endif
                        @if ($settings['company_phone']) · {{ $settings['company_phone'] }} @endif
                    </div>
                </td>
                <td class="doc-title">
                    <h1>FACTURE D'ABONNEMENT</h1>
                    <div class="num">{{ $payment->invoice_number }}</div>
                    <div class="meta">Date : {{ $payment->paid_at->format('d/m/Y') }}</div>
                </td>
            </tr>
        </table>
    </div>

    <table class="parties">
        <tr>
            <td>
                <div class="label">Facturé à</div>
                <strong>{{ $organisation?->name }}</strong><br>
                @if ($organisation?->address) {{ $organisation->address }}<br> @endif
                @if ($organisation?->city) {{ $organisation->city->name }}@if ($organisation?->country), {{ $organisation->country->name }}@endif<br> @endif
                @if ($organisation?->email) {{ $organisation->email }} @endif
                @if ($organisation?->phone) · {{ $organisation->phone }} @endif
                @if ($organisation?->cnps_employer_number)<br>N° CNPS : {{ $organisation->cnps_employer_number }} @endif
            </td>
            <td>
                <div class="label">Abonnement</div>
                Plan <strong>{{ $payment->plan_name }}</strong> · {{ $payment->billing_cycle?->getLabel() }}<br>
                Période couverte : {{ $payment->periodLabel() }}
            </td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th>Désignation</th>
                <th>Période</th>
                <th class="right">Montant</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Abonnement GESTAVI — plan {{ $payment->plan_name }} ({{ mb_strtolower($payment->billing_cycle?->getLabel() ?? '') }})</td>
                <td>{{ $payment->periodLabel() }}</td>
                <td class="right">{{ $fmt($payment->amount) }}</td>
            </tr>
        </tbody>
    </table>

    <table class="totals">
        <tr class="grand">
            <td style="width: 70%" class="right">Total réglé</td>
            <td class="right">{{ $fmt($payment->amount) }}</td>
        </tr>
    </table>

    <div class="payment">
        <strong>Règlement reçu</strong> le {{ $payment->paid_at->format('d/m/Y') }}
        @if ($payment->payment_method) par {{ mb_strtolower($payment->payment_method->getLabel()) }} @endif
        @if ($payment->payment_reference) — référence {{ $payment->payment_reference }} @endif.
        Cette facture est acquittée.
        @if ($payment->notes)<br>{{ $payment->notes }} @endif
    </div>

    <div class="footer">
        {{ $settings['company_name'] }} — facture générée le {{ now()->format('d/m/Y à H:i') }}.
        @if ($settings['support_email']) Questions : {{ $settings['support_email'] }} @endif
    </div>
</div>
</body>
</html>
