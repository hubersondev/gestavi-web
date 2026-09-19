<?php

namespace App\Filament\Farm\Pages\Reports\Concerns;

use App\Jobs\GenerateReportExport;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exports des pages de rapport :
 * - file d'attente configurée → le fichier est généré en arrière-plan par
 *   GenerateReportExport et l'utilisateur est notifié avec un lien de téléchargement ;
 * - file `sync` (dev/test) → génération immédiate et téléchargement direct.
 *
 * La page hôte implémente `reportKey()`, `buildXlsx(): string` et, si elle
 * propose un PDF, `buildPdf(): string`.
 */
trait ExportsReport
{
    /**
     * Filtres courants, normalisés (période par défaut : mois en cours).
     *
     * @return array<string, mixed>
     */
    public function normalizedFilters(): array
    {
        $filters = $this->filters ?? [];
        $filters['date_from'] = $filters['date_from'] ?? now()->startOfMonth()->toDateString();
        $filters['date_to'] = $filters['date_to'] ?? now()->toDateString();

        return $filters;
    }

    public function exportExcel(): ?StreamedResponse
    {
        return $this->dispatchOrStream('xlsx');
    }

    public function exportPdf(): ?StreamedResponse
    {
        return $this->dispatchOrStream('pdf');
    }

    protected function dispatchOrStream(string $format): ?StreamedResponse
    {
        $filename = sprintf('rapport-%s-%s.%s', $this->reportKey(), $this->normalizedFilters()['date_from'], $format);

        if (config('queue.default') === 'sync') {
            $content = $format === 'pdf' ? $this->buildPdf() : $this->buildXlsx();

            return response()->streamDownload(
                fn () => print ($content),
                $filename,
                ['Content-Type' => GenerateReportExport::contentType($format)],
            );
        }

        GenerateReportExport::dispatch(
            report: $this->reportKey(),
            format: $format,
            filters: $this->normalizedFilters(),
            organisationId: (int) Filament::getTenant()?->getKey(),
            userId: (int) Filament::auth()->id(),
        );

        Notification::make()
            ->title('Export en préparation')
            ->body('Le fichier '.$filename.' est en cours de génération. Vous recevrez une notification avec le lien de téléchargement.')
            ->info()
            ->send();

        return null;
    }

    /**
     * Génère un classeur XLSX en mémoire : $writeRows reçoit une closure `addRow(array $values)`.
     *
     * @param  callable(callable(array<int, mixed>): void): void  $writeRows
     */
    protected function xlsxToString(callable $writeRows): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'gestavi-xlsx-');

        $writer = new Writer;
        $writer->openToFile($tempFile);

        $writeRows(fn (array $values): mixed => $writer->addRow(Row::fromValues($values)));

        $writer->close();

        $content = (string) file_get_contents($tempFile);
        @unlink($tempFile);

        return $content;
    }
}
