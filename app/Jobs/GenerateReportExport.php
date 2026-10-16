<?php

namespace App\Jobs;

use App\Filament\Farm\Pages\Reports\AccountingReportPage;
use App\Filament\Farm\Pages\Reports\CommercialReportPage;
use App\Filament\Farm\Pages\Reports\FinancialReportPage;
use App\Filament\Farm\Pages\Reports\HrReportPage;
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

    /**
     * Clé d'export → page de rapport, ou [page, méthode XLSX] quand l'export n'est pas
     * le classeur standard de la page (ex. détail de la paie depuis le rapport RH).
     *
     * @var array<string, class-string|array{0: class-string, 1: string}>
     */
    public const REPORTS = [
        'production' => ProductionReportPage::class,
        'financier' => FinancialReportPage::class,
        'commercial' => CommercialReportPage::class,
        'stocks' => StockReportPage::class,
        'comptabilite' => AccountingReportPage::class,
        'rh' => HrReportPage::class,
        'paie' => [HrReportPage::class, 'buildPayrollXlsx'],
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

    /**
     * @return class-string
     */
    public static function pageClass(string $report): string
    {
        $definition = self::REPORTS[$report] ?? throw new \InvalidArgumentException("Rapport inconnu : {$report}");

        return is_array($definition) ? $definition[0] : $definition;
    }

    /**
     * Génère le contenu demandé à partir d'une page déjà munie de ses filtres.
     */
    public static function build(object $page, string $report, string $format): string
    {
        if ($format === 'pdf') {
            return $page->buildPdf();
        }

        $definition = self::REPORTS[$report] ?? throw new \InvalidArgumentException("Rapport inconnu : {$report}");
        $method = is_array($definition) ? $definition[1] : 'buildXlsx';

        return $page->{$method}();
    }

    public function handle(): string
    {
        $pageClass = self::pageClass($this->report);

        $user = User::query()->findOrFail($this->userId);
        $organisation = Organisation::query()->findOrFail($this->organisationId);

        Auth::login($user);
        Filament::setCurrentPanel(Filament::getPanel('farm'));
        Filament::setTenant($organisation);

        /** @var object{filters: array<string, mixed>} $page */
        $page = app($pageClass);
        $page->filters = $this->filters;

        $content = self::build($page, $this->report, $this->format);

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
