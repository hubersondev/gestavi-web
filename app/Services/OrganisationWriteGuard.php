<?php

namespace App\Services;

use App\Enums\OrganisationStatus;
use App\Exceptions\OrganisationReadOnlyException;
use App\Models\Organisation;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;

/**
 * Garde-fou d'écriture : dans l'espace d'une organisation (panel Ferme ou API), toute création,
 * modification ou suppression d'un enregistrement rattaché à une organisation en lecture seule
 * ou suspendue est refusée au niveau Eloquent, quel que soit le chemin (ressource, action de
 * page, synchronisation, relation). Les traitements planifiés (hors requête) ne sont pas
 * concernés : ils continuent d'agréger, prévoir et alerter.
 *
 * Les données propres à l'utilisateur (profil, préférences, notifications) ne portent pas
 * d'organisation et restent modifiables.
 */
class OrganisationWriteGuard
{
    /** @var array<int, OrganisationStatus> */
    private static array $statusCache = [];

    public static function register(): void
    {
        Event::listen(
            ['eloquent.creating: *', 'eloquent.updating: *', 'eloquent.deleting: *', 'eloquent.restoring: *'],
            function (string $event, array $payload): void {
                $model = $payload[0] ?? null;

                if ($model instanceof Model) {
                    self::check($model);
                }
            },
        );
    }

    /**
     * Refuse l'écriture si le contexte courant est l'espace d'une organisation qui n'autorise
     * pas les écritures et si le modèle lui appartient.
     */
    public static function check(Model $model): void
    {
        $context = self::contextOrganisation();

        if (! $context) {
            return;
        }

        $organisationId = $model instanceof Organisation
            ? (int) $model->getKey()
            : (int) ($model->getAttribute('organisation_id') ?? 0);

        if ($organisationId === 0) {
            return;
        }

        $status = $organisationId === (int) $context->getKey()
            ? $context->status
            : self::statusOf($organisationId);

        if ($status->allowsWrites()) {
            return;
        }

        $organisation = $organisationId === (int) $context->getKey() ? $context : Organisation::query()->find($organisationId);

        throw OrganisationReadOnlyException::forOrganisation($organisation?->name ?? 'inconnue', $organisation?->status_reason);
    }

    /**
     * Organisation dont l'espace est en cours d'utilisation : le tenant du panel Ferme. Null hors
     * de ce contexte (admin, console, API — l'API est protégée par le middleware
     * `organisation.status`, qui refuse les écritures avant tout traitement).
     */
    public static function contextOrganisation(): ?Organisation
    {
        $tenant = Filament::getTenant();

        if ($tenant instanceof Organisation && Filament::getCurrentPanel()?->getId() === 'farm') {
            return $tenant;
        }

        return null;
    }

    public static function flushCache(): void
    {
        self::$statusCache = [];
    }

    private static function statusOf(int $organisationId): OrganisationStatus
    {
        if (! isset(self::$statusCache[$organisationId])) {
            // Le cast du modèle peut déjà renvoyer le cas de l'énumération
            $value = Organisation::query()->whereKey($organisationId)->value('status');
            self::$statusCache[$organisationId] = $value instanceof OrganisationStatus
                ? $value
                : (OrganisationStatus::tryFrom((string) $value) ?? OrganisationStatus::Active);
        }

        return self::$statusCache[$organisationId];
    }
}
