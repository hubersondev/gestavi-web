<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use App\Services\TheftAlert;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipmentMovement extends Model
{
    use BelongsToOrganisation, HasFactory;

    /** Parent métier dont héritent organisation_id et farm_id. */
    protected static string $organisationParentRelation = 'equipment';

    protected $fillable = [
        'organisation_id', 'farm_id',
        'equipment_id',
        'moved_by',
        'from_building_id',
        'to_building_id',
        'movement_type',
        'movement_date',
        'reason',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'movement_date' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (EquipmentMovement $movement): void {
            if (! $movement->isTheft()) {
                return;
            }

            $equipment = $movement->equipment;

            if (! $equipment) {
                return;
            }

            // Le matériel volé sort du parc (réformé) sans attendre une saisie séparée
            $equipment->dispose();

            TheftAlert::dispatch(
                (int) ($movement->organisation_id ?? $equipment->organisation_id),
                'Vol de matériel : '.$equipment->name,
                [
                    '• '.($equipment->inventory_number ? 'N° '.$equipment->inventory_number.' — ' : '').'valeur '.TheftAlert::number((float) ($equipment->current_value ?? $equipment->purchase_price ?? 0)).' XOF',
                    '• '.($movement->fromBuilding?->name ? 'Depuis '.$movement->fromBuilding->name : 'Lieu non précisé').($movement->movement_date ? ' le '.$movement->movement_date->format('d/m/Y') : ''),
                    ...(filled($movement->reason) ? ['• '.$movement->reason] : []),
                ],
            );
        });
    }

    // ===== RELATIONS =====

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function mover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moved_by');
    }

    public function fromBuilding(): BelongsTo
    {
        return $this->belongsTo(Building::class, 'from_building_id');
    }

    public function toBuilding(): BelongsTo
    {
        return $this->belongsTo(Building::class, 'to_building_id');
    }

    // ===== SCOPES =====

    public function scopeTransfers($query)
    {
        return $query->where('movement_type', 'transfer');
    }

    public function scopeEntries($query)
    {
        return $query->where('movement_type', 'entry');
    }

    public function scopeExits($query)
    {
        return $query->where('movement_type', 'exit');
    }

    public function scopeForDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('movement_date', [$startDate, $endDate]);
    }

    // ===== HELPERS =====

    public function isTransfer(): bool
    {
        return $this->movement_type === 'transfer';
    }

    public function isEntry(): bool
    {
        return $this->movement_type === 'entry';
    }

    public function isExit(): bool
    {
        return $this->movement_type === 'exit';
    }

    public function isLoan(): bool
    {
        return $this->movement_type === 'loan';
    }

    public function isReturn(): bool
    {
        return $this->movement_type === 'return';
    }

    public function isTheft(): bool
    {
        return $this->movement_type === 'theft';
    }

    public function getTypeLabel(): string
    {
        return match ($this->movement_type) {
            'transfer' => 'Transfert',
            'entry' => 'Entrée',
            'exit' => 'Sortie',
            'loan' => 'Prêt',
            'return' => 'Retour',
            'theft' => 'Vol',
            default => $this->movement_type,
        };
    }

    public function getDescription(): string
    {
        $from = $this->fromBuilding?->name ?? 'Stock';
        $to = $this->toBuilding?->name ?? 'Stock';

        return match ($this->movement_type) {
            'transfer' => "Transfert de {$from} vers {$to}",
            'entry' => "Entrée dans {$to}",
            'exit' => "Sortie de {$from}",
            'loan' => "Prêt depuis {$from}",
            'return' => "Retour vers {$to}",
            'theft' => "Vol depuis {$from}",
            default => $this->movement_type,
        };
    }
}
