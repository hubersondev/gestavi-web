<?php

namespace App\Services\Imports;

use Closure;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Lit la première feuille d'un fichier Excel (.xlsx) ou CSV en lignes indexées par en-tête normalisé
 * (minuscules, sans accent, espaces remplacés par des soulignés : « Prix unitaire » → prix_unitaire).
 *
 * Le format est reconnu au contenu, pas à l'extension : un fichier envoyé par le navigateur est
 * enregistré sous une extension devinée (souvent .txt pour un CSV). Pour un CSV, l'encodage
 * (UTF-8 ou Windows-1252 des exports Excel français) et le séparateur sont détectés.
 */
class SpreadsheetReader
{
    /** Taille de l'échantillon lu pour reconnaître le format, l'encodage et le séparateur. */
    private const int SAMPLE_BYTES = 65536;

    /**
     * @param  (Closure(list<string>): bool)|null  $isHeader  Reconnaît la ligne d'en-tête parmi les premières
     *                                                        lignes non vides (titre ou préambule d'un relevé
     *                                                        ignorés) ; sans elle, la première ligne non vide.
     * @return array{headers: list<string>, rows: list<array{line: int, values: array<string, mixed>}>}
     */
    public function read(string $path, ?Closure $isHeader = null): array
    {
        $reader = $this->openReader($path);

        $headers = [];
        $rows = [];

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $lineNumber => $row) {
                    $cells = array_map(fn (Cell $cell): mixed => $this->normalizeCell($cell), $row->getCells());

                    if ($this->isBlank($cells)) {
                        continue;
                    }

                    if ($headers === []) {
                        $candidate = array_map(fn (mixed $cell): string => static::normalizeHeader((string) $cell), $cells);

                        if ($isHeader === null || $isHeader(array_values(array_filter($candidate)))) {
                            $headers = $candidate;
                        }

                        continue;
                    }

                    $values = [];

                    foreach ($headers as $index => $header) {
                        if ($header === '') {
                            continue;
                        }

                        $values[$header] = $cells[$index] ?? null;
                    }

                    $rows[] = ['line' => $lineNumber, 'values' => $values];
                }

                // Seule la première feuille porte les données ; les suivantes sont de l'aide
                break;
            }
        } finally {
            $reader->close();
        }

        return ['headers' => array_values(array_filter($headers)), 'rows' => $rows];
    }

    public static function normalizeHeader(string $header): string
    {
        return (string) Str::of($header)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_');
    }

    private function openReader(string $path): CsvReader|XlsxReader
    {
        $sample = (string) @file_get_contents($path, false, null, 0, self::SAMPLE_BYTES);

        if (str_starts_with($sample, "\xD0\xCF\x11\xE0")) {
            throw UnsupportedSpreadsheetException::legacyExcel();
        }

        $reader = str_starts_with($sample, "PK\x03\x04") ? new XlsxReader : new CsvReader(static::csvOptions($sample));
        $reader->open($path);

        return $reader;
    }

    /**
     * Encodage et séparateur de champs déduits de l'échantillon : Windows-1252 quand le contenu n'est
     * pas de l'UTF-8 valide ; séparateur le plus fréquent (; , tabulation |) sur la première ligne non vide.
     */
    private static function csvOptions(string $sample): CsvOptions
    {
        $options = new CsvOptions;
        $text = str_starts_with($sample, "\xEF\xBB\xBF") ? substr($sample, 3) : $sample;

        // Un échantillon tronqué peut couper un caractère multi-octets : on ignore la fin
        if (! mb_check_encoding(substr($text, 0, max(0, strlen($text) - 4)), 'UTF-8')) {
            $options->ENCODING = 'Windows-1252';
        }

        $firstLine = '';

        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
            if (trim($line) !== '') {
                $firstLine = $line;

                break;
            }
        }

        $counts = [];

        foreach ([';', ',', "\t", '|'] as $delimiter) {
            $counts[$delimiter] = substr_count($firstLine, $delimiter);
        }

        arsort($counts);
        $best = (string) array_key_first($counts);

        if ($counts[$best] > 0) {
            $options->FIELD_DELIMITER = $best;
        }

        return $options;
    }

    /** Valeur d'une cellule : résultat calculé pour une formule, date au format Y-m-d, texte rogné. */
    private function normalizeCell(Cell $cell): mixed
    {
        $value = $cell instanceof FormulaCell ? $cell->getComputedValue() : $cell->getValue();

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : $value;
        }

        return $value;
    }

    /**
     * @param  list<mixed>  $cells
     */
    private function isBlank(array $cells): bool
    {
        foreach ($cells as $cell) {
            if ($cell !== null && $cell !== '') {
                return false;
            }
        }

        return true;
    }
}
