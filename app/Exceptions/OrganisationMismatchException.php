<?php

namespace App\Exceptions;

use InvalidArgumentException;

/**
 * Levée quand un enregistrement référence une ferme (ou un parent) qui
 * n'appartient pas à son organisation : garde-fou du cloisonnement multi-tenant.
 */
class OrganisationMismatchException extends InvalidArgumentException
{
    public static function farmOutsideOrganisation(string $model, int $farmId, int $organisationId): self
    {
        return new self(sprintf(
            '%s : la ferme #%d n\'appartient pas à l\'organisation #%d.',
            class_basename($model),
            $farmId,
            $organisationId,
        ));
    }

    public static function parentOutsideOrganisation(string $model, string $relation, int $organisationId): self
    {
        return new self(sprintf(
            '%s : l\'enregistrement parent « %s » n\'appartient pas à l\'organisation #%d.',
            class_basename($model),
            $relation,
            $organisationId,
        ));
    }
}
