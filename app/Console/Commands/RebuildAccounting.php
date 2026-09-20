<?php

namespace App\Console\Commands;

use App\Models\Organisation;
use App\Services\AccountingService;
use Illuminate\Console\Command;

class RebuildAccounting extends Command
{
    protected $signature = 'gestavi:accounting-rebuild {--organisation= : Identifiant de l\'organisation (toutes si absent)}';

    protected $description = 'Supprime les écritures comptables auto-générées et les rejoue depuis les transactions, factures, dépenses et bulletins existants';

    public function handle(AccountingService $accounting): int
    {
        $organisations = Organisation::query()
            ->when($this->option('organisation'), fn ($q, $id) => $q->whereKey($id))
            ->get();

        foreach ($organisations as $organisation) {
            $counts = $accounting->rebuild($organisation->id);

            $this->info(sprintf(
                '%s : %d facture(s), %d dépense(s), %d bulletin(s), %d transaction(s) comptabilisés.',
                $organisation->name,
                $counts['sales'],
                $counts['expenses'],
                $counts['payrolls'],
                $counts['transactions'],
            ));
        }

        return self::SUCCESS;
    }
}
