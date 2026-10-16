<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Écriture refusée : l'organisation est en lecture seule (ou suspendue). Rendue en 423 Locked
 * pour l'API et les requêtes Livewire, en page d'erreur 423 sinon.
 */
class OrganisationReadOnlyException extends Exception
{
    public static function forOrganisation(string $organisationName, ?string $reason = null): self
    {
        $message = "L'organisation « {$organisationName} » est en lecture seule : aucune création, modification ni suppression n'est possible.";

        if (filled($reason)) {
            $message .= ' Motif : '.$reason;
        }

        return new self($message);
    }

    public function render(Request $request): Response
    {
        if ($request->expectsJson() || $request->hasHeader('X-Livewire')) {
            return new JsonResponse(['message' => $this->getMessage(), 'status' => 'read_only'], Response::HTTP_LOCKED);
        }

        abort(Response::HTTP_LOCKED, $this->getMessage());
    }
}
