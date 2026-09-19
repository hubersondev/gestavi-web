<?php

namespace App\Jobs;

use App\Filament\Farm\Pages\Reports\CommercialReportPage;
use App\Filament\Farm\Pages\Reports\FinancialReportPage;
use App\Filament\Farm\Pages\Reports\ProductionReportPage;
use App\Filament\Farm\Pages\Reports\StockReportPage;
use App\Models\Organisation;
use App\Models\User;
use App\Notifications\OrganisationAlertNotification;
use Filament\Facades\Filament;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Génère un export de rapport (PDF ou XLSX) hors requête HTTP, le stocke sur
 * le disque privé `local` (exports/{organisation}/{uuid}.{ext}) puis notifie
 * l'utilisateur avec un lien de téléchargement signé, valable 24 h.
 *
 * Le contexte Filament (panel, tenant, utilisateur) est reconstitué pour que
 * les scopes ferme/organisation des pages s'appliquent à l'identique.
 */
class GenerateReportExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const REPORTS = [
        'production' => ProductionReportPage::class,
        'financier' => FinancialReportPage::class,
        'commercial' => CommercialReportPage::class,
        'stocks' => StockReportPage::class,
    ];

    public int $tries = 2;

    public int $timeout = 600;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        public readonly string $report,
        public readonly string $format,
        public readonly array $filters,
        public readonly int $organisationId,
        public readonly int $userId,
    ) {}

    public static function contentType(string $format): string
    {
        return $format === 'pdf'
            ? 'application/pdf'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    }

    public function handle(): string
    {
        $pageClass = self::REPORTS[$this->report] ?? throw new \InvalidArgumentException("Rapport inconnu : {$this->report}");

        $user = User::query()->findOrFail($this->userId);
        $organisation = Organisation::query()->findOrFail($this->organisationId);

        Auth::login($user);
        Filament::setCurrentPanel(Filament::getPanel('farm'));
        Filament::setTenant($organisation);

        /** @var object{filters: array<string, mixed>} $page */
        $page = app($pageClass);
        $page->filters = $this->filters;

        $content = $this->format === 'pdf' ? $page->buildPdf() : $page->buildXlsx();

        $path = sprintf('exports/%d/%s.%s', $organisation->id, Str::uuid(), $this->format);
        Storage::disk('local')->put($path, $content);

        $user->notify(new OrganisationAlertNotification(
            title: 'Export prêt : rapport '.$this->report,
            lines: [
                sprintf('Période du %s au %s.', $this->filters['date_from'] ?? '—', $this->filters['date_to'] ?? '—'),
                'Le lien de téléchargement est valable 24 heures.',
            ],
            alertKey: 'report_export',
            organisationId: $organisation->id,
            status: 'success',
            icon: 'heroicon-o-arrow-down-tray',
            actionLabel: 'Télécharger',
            actionUrl: $this->downloadUrl($path),
        ));

        return $path;
    }

    private function downloadUrl(string $path): ?string
    {
        try {
            return Storage::disk('local')->temporaryUrl($path, now()->addHours(24));
        } catch (\Throwable) {
            // Disque sans URL temporaire (tests, driver exotique) : la notification reste informative
            return null;
        }
    }
}
