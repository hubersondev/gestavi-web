<?php

namespace App\Models\Concerns;

/**
 * Les lignes de document recalculent les totaux du parent à chaque écriture.
 * Lors de la persistance d'un lot de lignes (Repeater, import), les services
 * suspendent ce recalcul puis l'exécutent une seule fois.
 */
trait RecalculatesParentTotals
{
    protected static bool $parentRecalculationSuspended = false;

    /**
     * Exécute le callback sans recalcul du parent à chaque ligne.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public static function withoutParentRecalculation(callable $callback): mixed
    {
        $previous = static::$parentRecalculationSuspended;
        static::$parentRecalculationSuspended = true;

        try {
            return $callback();
        } finally {
            static::$parentRecalculationSuspended = $previous;
        }
    }

    public static function isParentRecalculationSuspended(): bool
    {
        return static::$parentRecalculationSuspended;
    }
}
