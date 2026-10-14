<?php

namespace App\Services\Imports;

use Exception;

/**
 * Levée volontairement à la fin d'une répétition à blanc pour annuler la transaction : la ligne
 * a été acceptée sans être conservée.
 */
class DryRunRollback extends Exception {}
