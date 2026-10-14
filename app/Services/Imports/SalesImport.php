<?php

namespace App\Services\Imports;

use App\Enums\SaleStatus;
use App\Services\SaleService;

/**
 * Factures : une ligne par article. Les lignes qui partagent le même numéro forment une facture ;
 * sans numéro, chaque ligne est une facture à part. Le numéro du fichier est conservé (continuité
 * de la numérotation), sinon il est généré dans l'année de la date.
 */
class SalesImport extends ImportDefinition
{
    public function key(): string
    {
        return 'sales';
    }

    public function label(): string
    {
        return 'Factures de vente';
    }

    public function description(): string
    {
        return 'Une ligne par article facturé ; les lignes de même numéro forment une facture. Les clients doivent exister.';
    }

    public function columns(): array
    {
        return [
            'numero' => ['Numéro', false, 'Numéro de facture d\'origine ; les lignes de même numéro sont regroupées'],
            'date' => ['Date', true, 'JJ/MM/AAAA'],
            'client' => ['Client', true, 'Nom ou code d\'un client existant'],
            'designation' => ['Désignation', true, 'Article vendu'],
            'quantite' => ['Quantité', true, ''],
            'prix_unitaire' => ['Prix unitaire', true, 'Hors taxe, en XOF'],
            'tva' => ['TVA %', false, 'Taux appliqué à la facture (0 si exonéré)'],
            'echeance' => ['Échéance', false, 'JJ/MM/AAAA'],
            'ferme' => ['Ferme', false, 'Nom ou code, obligatoire si plusieurs fermes'],
            'paye' => ['Payée', false, 'oui si la facture a été encaissée en totalité à sa date'],
            'mode_paiement' => ['Mode de paiement', false, 'especes, virement, cheque, orange_money, mtn_momo, moov_money, wave'],
            'compte' => ['Compte', false, 'Compte de trésorerie encaissé (nom ou code), vide = compte par défaut'],
        ];
    }

    public function exampleRows(): array
    {
        return [
            ['numero' => 'F2025-001', 'date' => '15/03/2025', 'client' => 'Maquis Chez Tantie', 'designation' => 'Plateau de 30 œufs', 'quantite' => 50, 'prix_unitaire' => 3000, 'tva' => 0, 'echeance' => '', 'ferme' => '', 'paye' => 'oui', 'mode_paiement' => 'especes', 'compte' => ''],
            ['numero' => 'F2025-001', 'date' => '15/03/2025', 'client' => 'Maquis Chez Tantie', 'designation' => 'Poulet de chair vif', 'quantite' => 20, 'prix_unitaire' => 3500, 'tva' => 0, 'echeance' => '', 'ferme' => '', 'paye' => 'oui', 'mode_paiement' => 'especes', 'compte' => ''],
            ['numero' => '', 'date' => '20/03/2025', 'client' => 'Koffi Aya', 'designation' => 'Plateau de 30 œufs', 'quantite' => 2, 'prix_unitaire' => 3000, 'tva' => 0, 'echeance' => '', 'ferme' => '', 'paye' => 'non', 'mode_paiement' => '', 'compte' => ''],
        ];
    }

    public function importRow(array $values, int $line, ImportContext $context): void
    {
        $this->importRows([['line' => $line, 'values' => $values]], $context);
    }

    public function importRows(array $rows, ImportContext $context): int
    {
        $groups = [];

        foreach ($rows as $index => $row) {
            $number = $context->string($row['values'], 'numero');
            $groups[$number !== null && $number !== '' ? 'n:'.$number : 'l:'.$index][] = $row;
        }

        $service = app(SaleService::class);

        foreach ($groups as $group) {
            $first = $group[0];
            $values = $first['values'];
            $line = $first['line'];

            $items = [];

            foreach ($group as $row) {
                $items[] = [
                    'product_name' => $context->requiredString($row['values'], 'designation', $row['line'], 'Désignation'),
                    'quantity' => $context->requiredNumber($row['values'], 'quantite', $row['line'], 'Quantité'),
                    'unit_price' => $context->requiredNumber($row['values'], 'prix_unitaire', $row['line'], 'Prix unitaire'),
                ];
            }

            $date = $context->requiredDate($values, 'date', $line, 'Date');
            $number = $context->string($values, 'numero');

            $sale = $service->createSale(array_filter([
                'organisation_id' => $context->organisation->id,
                'farm_id' => $context->farm($values, $line)?->id,
                'customer_id' => $context->customer($values, $line)->id,
                'invoice_number' => $number ?: null,
                'sale_date' => $date->toDateString(),
                'due_date' => $context->date($values, 'echeance', $line, 'Échéance')?->toDateString(),
                'status' => SaleStatus::Pending,
                'tax_percent' => $context->number($values, 'tva', $line, 'TVA %', 0.0),
                'user_id' => $context->userId,
            ], fn (mixed $value): bool => $value !== null), $items);

            if ($context->bool($values, 'paye')) {
                $account = $context->account($values, $line);

                $service->recordPayment(
                    $sale->fresh(),
                    (float) $sale->fresh()->total,
                    $context->paymentMethod($values, 'mode_paiement', $line),
                    $account?->id,
                    null,
                    $date,
                );
            }
        }

        return count($groups);
    }
}
