<?php

namespace App\Services\Imports;

use Illuminate\Support\Facades\Storage;

/**
 * Fichier d'import déposé par le composant d'envoi Filament sur le disque local, dans `imports/`.
 * L'état du formulaire vient du navigateur : le chemin est vérifié avant toute lecture ou
 * suppression, pour qu'un chemin forgé ne désigne jamais un autre fichier du disque.
 */
class UploadedImportFile
{
    public const string DISK = 'local';

    public const string DIRECTORY = 'imports';

    private function __construct(public readonly string $relativePath) {}

    /**
     * @param  string|array<array-key, string>|null  $state  État du FileUpload après enregistrement
     */
    public static function fromState(string|array|null $state): self
    {
        $file = is_array($state) ? reset($state) : $state;

        if (! is_string($file) || preg_match('#^'.self::DIRECTORY.'/[A-Za-z0-9][A-Za-z0-9._-]*$#', $file) !== 1 || str_contains($file, '..')) {
            throw UnsupportedSpreadsheetException::invalidUpload();
        }

        if (! Storage::disk(self::DISK)->exists($file)) {
            throw UnsupportedSpreadsheetException::invalidUpload();
        }

        return new self($file);
    }

    public function absolutePath(): string
    {
        return Storage::disk(self::DISK)->path($this->relativePath);
    }

    public function delete(): void
    {
        Storage::disk(self::DISK)->delete($this->relativePath);
    }
}
