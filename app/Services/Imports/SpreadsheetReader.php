<?php

namespace App\Services\Imports;

use Illuminate\Support\Str;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Lit la première feuille d'un fichier Excel (.xlsx) ou CSV en lignes indexées par en-tête normalisé
 * (minuscules, sans accent, espaces remplacés par des soulignés : « Prix unitaire » → prix_unitaire).
 */
class SpreadsheetReader
{
    /**
     * @return array{headers: list<string>, rows: list<array{line: int, values: array<string, mixed>}>}
     */
    public function read(string $path): array
    {
        $reader = strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'csv' ? new CsvReader : new XlsxReader;
        $reader->open($path);

        $headers = [];
        $rows = [];

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $lineNumber => $row) {
                    $cells = array_map(fn (mixed $cell): mixed => $this->normalizeCell($cell), $row->toArray());

                    if ($headers === []) {
                        if ($this->isBlank($cells)) {
                            continue;
                        }

                        $headers = array_map(fn (mixed $cell): string => static::normalizeHeader((string) $cell), $cells);

                        continue;
                    }

                    if ($this->isBlank($cells)) {
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

    private function normalizeCell(mixed $cell): mixed
    {
        if ($cell instanceof \DateTimeInterface) {
            return $cell->format('Y-m-d');
        }

        if (is_string($cell)) {
            $cell = trim($cell);

            return $cell === '' ? null : $cell;
        }

        return $cell;
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
