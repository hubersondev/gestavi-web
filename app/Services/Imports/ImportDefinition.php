<?php

namespace App\Services\Imports;

/**
 * Définition d'un type d'import : colonnes attendues (clés normalisées), exemple de ligne pour
 * le modèle Excel, et création des enregistrements. Une ligne en erreur lève ImportRowException.
 */
abstract class ImportDefinition
{
    abstract public function key(): string;

    abstract public function label(): string;

    abstract public function description(): string;

    /**
     * Colonnes du fichier, dans l'ordre du modèle : clé normalisée => [libellé, obligatoire, aide].
     *
     * @return array<string, array{0: string, 1: bool, 2: string}>
     */
    abstract public function columns(): array;

    /**
     * Lignes d'exemple du modèle, indexées par clé de colonne.
     *
     * @return list<array<string, mixed>>
     */
    abstract public function exampleRows(): array;

    /**
     * Importe une ligne. Les définitions qui regroupent des lignes (factures) surchargent importRows().
     *
     * @param  array<string, mixed>  $values
     */
    abstract public function importRow(array $values, int $line, ImportContext $context): void;

    /**
     * @param  list<array{line: int, values: array<string, mixed>}>  $rows
     * @return int nombre d'enregistrements créés
     */
    public function importRows(array $rows, ImportContext $context): int
    {
        foreach ($rows as $row) {
            $this->importRow($row['values'], $row['line'], $context);
        }

        return count($rows);
    }

    /**
     * Colonnes obligatoires absentes de l'en-tête du fichier.
     *
     * @param  list<string>  $headers
     * @return list<string>
     */
    public function missingHeaders(array $headers): array
    {
        $missing = [];

        foreach ($this->columns() as $key => [$label, $required]) {
            if ($required && ! in_array($key, $headers, true)) {
                $missing[] = $label;
            }
        }

        return $missing;
    }

    /**
     * En-tête normalisé du fichier → clé de colonne : le libellé du modèle (« Date d'embauche »)
     * comme la clé brute (« date_embauche ») sont acceptés.
     *
     * @return array<string, string>
     */
    public function headerAliases(): array
    {
        $aliases = [];

        foreach ($this->columns() as $key => [$label]) {
            $aliases[$key] = $key;
            $aliases[SpreadsheetReader::normalizeHeader($label)] = $key;
        }

        return $aliases;
    }

    /**
     * @return list<string>
     */
    public function headerLabels(): array
    {
        return array_map(fn (array $column): string => $column[0], array_values($this->columns()));
    }
}
