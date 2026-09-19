@php
    /** @var \App\Models\Organisation $organisation */
    /** @var \App\Models\Flock|null $flock */
    /** @var array $kpis */
    /** @var \Illuminate\Support\Collection $records */
    $fmt = fn ($v, $d = 0) => number_format((float) $v, $d, ',', ' ');
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Rapport Production</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1a1a1a; line-height: 1.4; }
        .page { padding: 20px 25px; }

        .header { border-bottom: 2px solid #2d6a4f; padding-bottom: 8px; margin-bottom: 12px; }
        .header-table { width: 100%; border-collapse: collapse; }
        .header-table td { vertical-align: top; }
        .org-name { font-size: 14px; font-weight: bold; color: #2d6a4f; }
        .org-meta { font-size: 8px; color: #555; }
        .doc-title h1 { font-size: 13px; color: #2d6a4f; text-align: right; text-transform: uppercase; }
        .doc-title .meta { font-size: 9px; color: #666; text-align: right; }

        .kpi-grid { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .kpi-grid td {
            width: 20%; text-align: center; padding: 8px 4px;
            border: 1px solid #e0e0e0; background: #f0f7f3;
        }
        .kpi-grid .value { font-size: 16px; font-weight: bold; color: #2d6a4f; }
        .kpi-grid .label { font-size: 8px; color: #555; text-transform: uppercase; }

        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .data-table thead th {
            background: #2d6a4f; color: white; padding: 5px 3px;
            font-size: 7px; text-transform: uppercase; text-align: center;
        }
        .data-table tbody td {
            padding: 3px; border-bottom: 1px solid #eee; font-size: 8px; text-align: center;
        }
        .data-table tbody tr:nth-child(even) td { background: #f9fafb; }
        .text-right { text-align: right !important; }
        .text-left { text-align: left !important; }
        .text-danger { color: #c0392b; font-weight: bold; }

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
                        <h1>Rapport de Production</h1>
                        <div class="meta">Du {{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }} au {{ \Carbon\Carbon::parse($dateTo)->format('d/m/Y') }}</div>
                        @if ($flock)<div class="meta">Bande : {{ $flock->code }} — {{ $flock->name }}</div>@endif
                    </td>
                </tr>
            </table>
        </div>

        <table class="kpi-grid">
            <tr>
                <td><div class="value">{{ $kpis['days'] }}</div><div class="label">Relevés ({{ $kpis['flocks'] ?? 0 }} bandes)</div></td>
                <td><div class="value" style="color: #c0392b;">{{ $fmt($kpis['mortality']) }}</div><div class="label">Mortalité ({{ $fmt($kpis['mortality_rate'] ?? 0, 2) }} %)</div></td>
                <td><div class="value">{{ $fmt($kpis['culls'] ?? 0) }}</div><div class="label">Réformes</div></td>
                <td><div class="value">{{ $fmt($kpis['eggs'] ?? 0) }}</div><div class="label">Œufs ({{ $fmt($kpis['laying_rate'] ?? 0, 2) }} % ponte)</div></td>
                <td><div class="value">{{ $fmt($kpis['total_feed'], 1) }} kg</div><div class="label">Aliment ({{ $fmt($kpis['feed_per_bird_day'] ?? 0, 1) }} g/sujet/j)</div></td>
            </tr>
            @if (($kpis['ic'] ?? null) !== null || ($kpis['gmq'] ?? null) !== null)
                <tr>
                    <td><div class="value">{{ $fmt($kpis['avg_weight']) }} g</div><div class="label">Poids moyen</div></td>
                    <td><div class="value">{{ $kpis['ic'] !== null ? $fmt($kpis['ic'], 2) : '—' }}</div><div class="label">Indice de consommation</div></td>
                    <td><div class="value">{{ $kpis['gmq'] !== null ? $fmt($kpis['gmq'], 1).' g/j' : '—' }}</div><div class="label">GMQ</div></td>
                    <td><div class="value">{{ $kpis['ipe'] !== null ? $fmt($kpis['ipe'], 1) : '—' }}</div><div class="label">IPE / EPEF</div></td>
                    <td></td>
                </tr>
            @endif
        </table>

        @if (isset($farmComparison) && $farmComparison->count() > 1)
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="text-left">Ferme</th>
                        <th>Bandes</th>
                        <th>Relevés</th>
                        <th>Mortalité</th>
                        <th>Réformes</th>
                        <th>Œufs</th>
                        <th>Taux de ponte</th>
                        <th>Aliment (kg)</th>
                        <th>g/sujet/j</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($farmComparison as $row)
                        <tr>
                            <td class="text-left">{{ $row->farm_name }}</td>
                            <td>{{ $row->flocks }}</td>
                            <td>{{ $row->records }}</td>
                            <td class="text-danger">{{ $fmt($row->mortality) }}</td>
                            <td>{{ $fmt($row->culls) }}</td>
                            <td>{{ $fmt($row->eggs) }}</td>
                            <td>{{ $fmt($row->laying_rate, 2) }} %</td>
                            <td>{{ $fmt($row->total_feed, 1) }}</td>
                            <td>{{ $fmt($row->feed_per_bird_day, 1) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if (! empty($truncated))
            <p style="color: #c0392b; font-weight: bold; margin-bottom: 6px;">
                Rapport tronqué aux {{ $maxRows ?? 2000 }} premières lignes : utilisez l'export Excel pour le détail complet.
            </p>
        @endif

        <table class="data-table">
            <thead>
                <tr>
                    <th class="text-left">Date</th>
                    <th>Ferme</th>
                    <th>Bande</th>
                    <th>Âge</th>
                    <th>Mortalité</th>
                    <th>Réformes</th>
                    <th>Oeufs</th>
                    <th>Cassés</th>
                    <th>Poids (g)</th>
                    <th>Aliment (kg)</th>
                    <th>Eau (L)</th>
                    <th>T° min</th>
                    <th>T° max</th>
                    <th>Hum. (%)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($records as $r)
                    <tr>
                        <td class="text-left">{{ $r->record_date?->format('d/m') }}</td>
                        <td>{{ $r->flock?->building?->farm?->name }}</td>
                        <td>{{ $r->flock?->code }}</td>
                        <td>{{ $r->age_days }}j</td>
                        <td class="{{ ($r->mortality ?? 0) > 0 ? 'text-danger' : '' }}">{{ $r->mortality ?? 0 }}</td>
                        <td>{{ $r->culls ?? 0 }}</td>
                        <td>{{ $r->eggs_collected ?? 0 }}</td>
                        <td>{{ $r->eggs_broken ?? 0 }}</td>
                        <td>{{ $fmt($r->average_weight_g ?? 0) }}</td>
                        <td>{{ $fmt($r->feed_consumed_kg ?? 0, 1) }}</td>
                        <td>{{ $fmt($r->water_consumed_l ?? 0, 1) }}</td>
                        <td>{{ $r->temperature_min ?? '—' }}</td>
                        <td>{{ $r->temperature_max ?? '—' }}</td>
                        <td>{{ $r->humidity_percent ?? '—' }}</td>
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
