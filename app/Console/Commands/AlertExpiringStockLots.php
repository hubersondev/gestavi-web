<?php

namespace App\Console\Commands;

use App\Enums\StockLotStatus;
use App\Models\Organisation;
use App\Models\OrganisationUser;
use App\Models\StockLot;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;

class AlertExpiringStockLots extends Command
{
    protected $signature = 'gestavi:alert-expiring-stock-lots
                            {--days=7 : Fenêtre d\'alerte en jours}';

    protected $description = 'Envoie une notification Filament aux admins/owners des organisations ayant des lots de stock périmant dans les jours à venir.';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $now = now()->startOfDay();
        $limit = now()->addDays($days)->endOfDay();

        $this->info("Analyse des lots périmant dans les {$days} prochains jours…");

        $totalSent = 0;
        $totalOrganisations = 0;

        Organisation::query()
            ->chunkById(50, function ($organisations) use ($now, $limit, $days, &$totalSent, &$totalOrganisations): void {
                foreach ($organisations as $organisation) {
                    $lots = StockLot::query()
                        ->with('stock')
                        ->whereHas('stock', fn ($q) => $q->where('organisation_id', $organisation->id))
                        ->whereNotNull('expiry_date')
                        ->whereBetween('expiry_date', [$now, $limit])
                        ->whereNotIn('status', [StockLotStatus::Expired, StockLotStatus::Depleted])
                        ->where('current_quantity', '>', 0)
                        ->orderBy('expiry_date')
                        ->get();

                    if ($lots->isEmpty()) {
                        continue;
                    }

                    $totalOrganisations++;

                    $recipients = OrganisationUser::query()
                        ->where('organisation_id', $organisation->id)
                        ->with(['user', 'organisationRole'])
                        ->get()
                        ->filter(fn (OrganisationUser $membership) => $membership->canAccessAllFarms())
                        ->map(fn (OrganisationUser $membership) => $membership->user)
                        ->filter();

                    if ($recipients->isEmpty()) {
                        continue;
                    }

                    $body = $lots->take(5)
                        ->map(fn (StockLot $lot): string => sprintf(
                            '• %s (%s) — périme le %s',
                            $lot->lot_number,
                            $lot->stock?->name ?? '—',
                            $lot->expiry_date?->format('d/m/Y') ?? '—',
                        ))
                        ->implode("\n");

                    if ($lots->count() > 5) {
                        $body .= "\n+ ".($lots->count() - 5).' autres lot(s)…';
                    }

                    Notification::make()
                        ->title($lots->count().' lot(s) vont périmer sous '.$days.' jours')
                        ->body($body)
                        ->warning()
                        ->icon('heroicon-o-clock')
                        ->sendToDatabase($recipients);

                    $totalSent += $recipients->count();

                    $this->line("  - {$organisation->name} : {$lots->count()} lots → ".$recipients->count().' notification(s)');
                }
            });

        if ($totalOrganisations === 0) {
            $this->info('Aucune organisation concernée.');

            return self::SUCCESS;
        }

        $this->info("{$totalOrganisations} organisation(s) traitée(s), {$totalSent} notification(s) envoyée(s).");

        return self::SUCCESS;
    }
}
