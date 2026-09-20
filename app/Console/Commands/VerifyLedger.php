<?php

namespace App\Console\Commands;

use App\Models\Organisation;
use App\Services\LedgerService;
use Illuminate\Console\Command;

class VerifyLedger extends Command
{
    protected $signature = 'gestavi:ledger-verify {--organisation= : Identifiant de l\'organisation (toutes si absent)}';

    protected $description = 'Rejoue la chaîne de hachage des écritures comptables et signale la première écriture altérée ou manquante';

    public function handle(LedgerService $ledger): int
    {
        $organisations = Organisation::query()
            ->when($this->option('organisation'), fn ($q, $id) => $q->whereKey($id))
            ->orderBy('id')
            ->get();

        $rows = [];
        $broken = 0;

        foreach ($organisations as $organisation) {
            $result = $ledger->verify($organisation->id);

            if (! $result['valid']) {
                $broken++;
            }

            $rows[] = [
                $organisation->id,
                $organisation->name,
                $result['checked'],
                $result['valid'] ? 'intègre' : 'ROMPUE',
                $result['valid'] ? '' : $result['broken_entry_number'].' ('.$result['reason'].')',
                $result['head_hash'] ? substr($result['head_hash'], 0, 16).'…' : '—',
            ];
        }

        $this->table(['Id', 'Organisation', 'Écritures', 'Chaîne', 'Rupture', 'Tête'], $rows);

        if ($broken > 0) {
            $this->error(sprintf('%d chaîne(s) rompue(s) : rapprochez la base des ancrages et de la dernière sauvegarde saine.', $broken));

            return self::FAILURE;
        }

        $this->info('Toutes les chaînes vérifiées sont intègres.');

        return self::SUCCESS;
    }
}
