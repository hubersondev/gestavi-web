<?php

namespace App\Services\Imports;

use RuntimeException;

/** Fichier envoyé qu'aucun lecteur ne sait ouvrir, ou chemin d'envoi invalide. */
class UnsupportedSpreadsheetException extends RuntimeException
{
    public static function legacyExcel(): self
    {
        return new self('Format Excel 97-2003 (.xls) non pris en charge : enregistrez le fichier en .xlsx ou en CSV, puis recommencez.');
    }

    public static function invalidUpload(): self
    {
        return new self('Fichier envoyé introuvable : recommencez l\'envoi.');
    }
}
