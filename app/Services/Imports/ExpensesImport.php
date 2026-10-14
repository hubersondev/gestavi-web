<?php

namespace App\Services\Imports;

use App\Enums\ExpenseCategory;
use App\Enums\ExpenseStatus;
use App\Services\ExpenseService;

class ExpensesImport extends ImportDefinition
{
    public function key(): string
    {
        return 'expenses';
    }

    public function label(): string
    {
        return 'Dépenses';
    }

    public function description(): string
    {
        return 'Une dépense par ligne. Un fournisseur inconnu est créé. Une dépense payée débite le compte à sa date.';
    }

    public function columns(): array
    {
        return [
            'date' => ['Date', true, 'JJ/MM/AAAA'],
            'categorie' => ['Catégorie', true, implode(', ', array_map(fn (ExpenseCategory $case): string => $case->getLabel(), ExpenseCategory::cases()))],
            'description' => ['Description', true, ''],
            'montant' => ['Montant', true, 'TTC en XOF'],
            'tva' => ['TVA %', false, 'Taux inclus dans le montant (0 si aucun)'],
            'fournisseur' => ['Fournisseur', false, 'Nom ; créé s\'il n\'existe pas'],
            'reference' => ['Référence', false, 'Numéro de facture du fournisseur'],
            'ferme' => ['Ferme', false, 'Nom ou code, obligatoire si plusieurs fermes'],
            'paye' => ['Payée', false, 'oui si réglée à sa date'],
            'compte' => ['Compte', false, 'Compte de trésorerie débité (nom ou code), vide = compte par défaut'],
        ];
    }

    public function exampleRows(): array
    {
        return [
            ['date' => '03/03/2025', 'categorie' => 'Aliment', 'description' => '40 sacs aliment démarrage', 'montant' => 740000, 'tva' => 0, 'fournisseur' => 'FACI', 'reference' => 'FA-2025-118', 'ferme' => '', 'paye' => 'oui', 'compte' => ''],
            ['date' => '10/03/2025', 'categorie' => 'Eau / Électricité', 'description' => 'Facture CIE février', 'montant' => 85000, 'tva' => 0, 'fournisseur' => '', 'reference' => '', 'ferme' => '', 'paye' => 'non', 'compte' => ''],
        ];
    }

    public function importRow(array $values, int $line, ImportContext $context): void
    {
        $aliases = [];

        foreach (ExpenseCategory::cases() as $case) {
            $aliases[SpreadsheetReader::normalizeHeader($case->getLabel())] = $case->value;
            $aliases[$case->value] = $case->value;

            // Chaque segment d'un libellé composé est accepté : « Eau », « Électricité »
            foreach (explode('/', $case->getLabel()) as $segment) {
                $aliases[SpreadsheetReader::normalizeHeader($segment)] ??= $case->value;
            }
        }

        $category = $context->choice($values, 'categorie', $line, 'Catégorie', $aliases);
        $date = $context->requiredDate($values, 'date', $line, 'Date');
        $amount = $context->requiredNumber($values, 'montant', $line, 'Montant');

        if ($amount <= 0) {
            throw new ImportRowException($line, 'le montant doit être positif.');
        }

        $taxPercent = $context->number($values, 'tva', $line, 'TVA %', 0.0);
        $paid = $context->bool($values, 'paye');
        $account = $paid ? $context->account($values, $line) : null;

        if ($paid && ! $account) {
            throw new ImportRowException($line, 'dépense payée : indiquez le compte de trésorerie débité (colonne « Compte ») ou définissez un compte par défaut.');
        }

        app(ExpenseService::class)->recordExpense([
            'organisation_id' => $context->organisation->id,
            'farm_id' => $context->farm($values, $line)?->id,
            'supplier_id' => $context->supplierOrCreate($values, $line)?->id,
            'account_id' => $account?->id,
            'user_id' => $context->userId,
            'expense_date' => $date->toDateString(),
            'category' => $category,
            'status' => ExpenseStatus::Unpaid,
            'amount' => $amount,
            'tax_percent' => $taxPercent,
            'tax_amount' => round($amount - $amount / (1 + $taxPercent / 100), 2),
            'balance' => $amount,
            'description' => $context->requiredString($values, 'description', $line, 'Description'),
            'reference' => $context->string($values, 'reference'),
        ], payNow: $paid, paidAt: $date);
    }
}
