<?php

namespace App\Services\Imports;

use App\Models\Organisation;
use Illuminate\Support\Facades\DB;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Import Excel des référentiels et des pièces d'une organisation : modèle téléchargeable par type,
 * contrôle complet du fichier avant toute écriture, puis import en une seule transaction.
 * Un fichier en erreur n'importe rien.
 */
class ExcelImportService
{
    /**
     * @return array<string, ImportDefinition>
     */
    public function definitions(): array
    {
        $definitions = [
            new CustomersImport,
            new SuppliersImport,
            new ProductsImport,
            new StocksImport,
            new EmployeesImport,
            new SalesImport,
            new ExpensesImport,
        ];

        return collect($definitions)->keyBy(fn (ImportDefinition $definition): string => $definition->key())->all();
    }

    public function definition(string $key): ImportDefinition
    {
        return $this->definitions()[$key] ?? throw new \InvalidArgumentException("Type d'import inconnu : {$key}");
    }

    /**
     * Écrit le modèle Excel d'un type (en-têtes, lignes d'exemple, feuille d'aide) et retourne son chemin.
     */
    public function writeTemplate(string $key, ?string $path = null): string
    {
        $definition = $this->definition($key);
        $path ??= tempnam(sys_get_temp_dir(), 'gestavi-modele-').'.xlsx';

        $writer = new Writer;
        $writer->openToFile($path);

        $bold = (new Style)->setFontBold();
        $columns = $definition->columns();

        $writer->addRow(Row::fromValues($definition->headerLabels(), $bold));

        foreach ($definition->exampleRows() as $example) {
            $writer->addRow(Row::fromValues(array_map(fn (string $column): mixed => $example[$column] ?? '', array_keys($columns))));
        }

        $help = $writer->addNewSheetAndMakeItCurrent();
        $help->setName('Aide');
        $writer->addRow(Row::fromValues(['Colonne', 'Obligatoire', 'Aide'], $bold));

        foreach ($columns as [$label, $required, $hint]) {
            $writer->addRow(Row::fromValues([$label, $required ? 'oui' : 'non', $hint]));
        }

        $writer->addRow(Row::fromValues(['']));
        $writer->addRow(Row::fromValues(['Conservez la ligne d\'en-tête, supprimez les lignes d\'exemple, une ligne par enregistrement. Dates au format JJ/MM/AAAA.']));
        $writer->addRow(Row::fromValues([$definition->description()]));

        $writer->close();

        return $path;
    }

    /**
     * Contrôle puis importe un fichier. Toute erreur annule l'ensemble.
     *
     * @return array{imported: int, rows: int, errors: list<string>}
     */
    public function import(string $key, string $path, Organisation $organisation, ?int $userId = null): array
    {
        $definition = $this->definition($key);
        $context = new ImportContext($organisation, $userId);

        ['headers' => $headers, 'rows' => $rows] = (new SpreadsheetReader)->read($path);

        // Les en-têtes du fichier peuvent être les libellés du modèle ou les clés de colonne
        $aliases = $definition->headerAliases();
        $headers = array_map(fn (string $header): string => $aliases[$header] ?? $header, $headers);

        foreach ($rows as &$row) {
            $values = [];

            foreach ($row['values'] as $header => $value) {
                $values[$aliases[$header] ?? $header] = $value;
            }

            $row['values'] = $values;
        }

        unset($row);

        $missing = $definition->missingHeaders($headers);

        if ($missing !== []) {
            return ['imported' => 0, 'rows' => count($rows), 'errors' => ['Colonnes obligatoires absentes de l\'en-tête : '.implode(', ', $missing).'. Repartez du modèle téléchargeable.']];
        }

        if ($rows === []) {
            return ['imported' => 0, 'rows' => 0, 'errors' => ['Le fichier ne contient aucune ligne de données sous l\'en-tête.']];
        }

        // Répétition à blanc dans une transaction annulée : toutes les erreurs du fichier sont rapportées d'un coup
        $errors = $this->dryRun($definition, $rows, $context);

        if ($errors !== []) {
            return ['imported' => 0, 'rows' => count($rows), 'errors' => $errors];
        }

        $imported = DB::transaction(fn (): int => $definition->importRows($rows, new ImportContext($organisation, $userId)));

        return ['imported' => $imported, 'rows' => count($rows), 'errors' => []];
    }

    /**
     * @param  list<array{line: int, values: array<string, mixed>}>  $rows
     * @return list<string>
     */
    private function dryRun(ImportDefinition $definition, array $rows, ImportContext $context): array
    {
        $errors = [];

        foreach ($rows as $row) {
            // Contexte neuf par ligne : le cache ne doit pas retenir des fournisseurs ou catégories créés puis annulés
            $rowContext = new ImportContext($context->organisation, $context->userId);

            try {
                DB::transaction(function () use ($definition, $row, $rowContext): void {
                    $definition->importRow($row['values'], $row['line'], $rowContext);

                    throw new DryRunRollback;
                });
            } catch (DryRunRollback) {
                continue;
            } catch (ImportRowException $e) {
                $errors[] = $e->getMessage();
            } catch (\Throwable $e) {
                $errors[] = "Ligne {$row['line']} : ".$e->getMessage();
            }

            if (count($errors) >= 50) {
                $errors[] = 'Plus de 50 erreurs : corrigez le fichier puis recommencez.';
                break;
            }
        }

        return $errors;
    }
}
