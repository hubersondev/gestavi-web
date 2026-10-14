<?php

namespace App\Services\Imports;

use Exception;

/**
 * Erreur sur une ligne d'un fichier d'import, avec son numéro de ligne dans le fichier.
 */
class ImportRowException extends Exception
{
    public function __construct(public readonly int $rowLine, string $message)
    {
        parent::__construct("Ligne {$rowLine} : {$message}");
    }
}
