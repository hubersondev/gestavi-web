<?php

namespace App\Console\Commands;

use App\Enums\LedgerAnchorReason;
use App\Exceptions\AccountingException;
use App\Models\JournalEntry;
use App\Models\Organisation;
use App\Services\LedgerService;
use Illuminate\Console\Command;

class AnchorLedger extends Command
{
    protected $signature = 'gestavi:ledger-anchor
                            {--organisation= : Identifiant de l\'organisation (toutes celles ayant des écritures si absent)}
                            {--reason=scheduled : Motif (scheduled, manual)}';

    protected $description = 'Vérifie chaque chaîne de hachage puis ancre sa tête en base et dans le fichier hors base (config ledger.anchor_disk)';

    public function handle(LedgerService $ledger): int
    {
        $reason = LedgerAnchorReason::tryFrom((string) $this->option('reason')) ?? LedgerAnchorReason::Scheduled;

        $organisations = Organisation::query()
            ->when($this->option('organisation'), fn ($q, $id) => $q->whereKey($id))
            ->whereIn('id', JournalEntry::query()->select('organisation_id'))
            ->orderBy('id')
            ->get();

        $failures = 0;

        foreach ($organisations as $organisation) {
            try {
                $anchor = $ledger->anchor($organisation->id, $reason);

                $this->info(sprintf('%s : %d écriture(s), tête %s…', $organisation->name, $anchor->entry_count, substr((string) $anchor->head_hash, 0, 16)));
            } catch (AccountingException $exception) {
                $failures++;
                $this->error($organisation->name.' : '.$exception->getMessage());
            }
        }

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }
}
