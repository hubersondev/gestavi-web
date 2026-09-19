<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class Equipment extends Model
{
    use HasFactory;

    protected $table = 'equipments';

    protected $fillable = [
        'building_id',
        'farm_id',
        'supplier_id',
        'name',
        'inventory_number',
        'serial_number',
        'category',
        'brand',
        'model',
        'capacity',
        'capacity_unit',
        'purchase_date',
        'purchase_price',
        'warranty_expiry_date',
        'status',
        'condition',
        'last_maintenance_date',
        'next_maintenance_date',
        'current_value',
        'lifespan_years',
        'purchase_reference',
        'maintenance_interval_days',
        'description',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'warranty_expiry_date' => 'date',
            'last_maintenance_date' => 'date',
            'next_maintenance_date' => 'date',
            'purchase_price' => 'decimal:2',
            'current_value' => 'decimal:2',
        ];
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(EquipmentMovement::class);
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(EquipmentMaintenance::class);
    }

    public function scheduledMaintenances(): HasMany
    {
        return $this->maintenances()->where('status', 'scheduled');
    }
    // ===== SCOPES =====

    public function scopeInService($query)
    {
        return $query->where('status', 'in_service');
    }

    public function scopeInStock($query)
    {
        return $query->where('status', 'in_stock');
    }

    public function scopeInMaintenance($query)
    {
        return $query->where('status', 'in_maintenance');
    }

    public function scopeOutOfService($query)
    {
        return $query->where('status', 'out_of_service');
    }

    public function scopeNeedsMaintenance($query)
    {
        return $query->where('next_maintenance_date', '<=', now());
    }

    public function scopeUnderWarranty($query)
    {
        return $query->whereNotNull('warranty_expiry_date')
            ->where('warranty_expiry_date', '>', now());
    }

    public function scopeForCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function scopeForBuilding($query, $buildingId)
    {
        return $query->where('building_id', $buildingId);
    }

    // ===== HELPERS =====

    public function isInService(): bool
    {
        return $this->status === 'in_service';
    }

    public function isInStock(): bool
    {
        return $this->status === 'in_stock';
    }

    public function isInMaintenance(): bool
    {
        return $this->status === 'in_maintenance';
    }

    public function isOutOfService(): bool
    {
        return $this->status === 'out_of_service';
    }

    public function isDisposed(): bool
    {
        return $this->status === 'disposed';
    }

    public function isUnderWarranty(): bool
    {
        return $this->warranty_expiry_date && $this->warranty_expiry_date > now();
    }

    public function isMaintenanceNeeded(): bool
    {
        return $this->next_maintenance_date && $this->next_maintenance_date <= now();
    }

    public function getDaysUntilMaintenance(): ?int
    {
        if (!$this->next_maintenance_date) {
            return null;
        }
        return now()->diffInDays($this->next_maintenance_date, false);
    }

    public function getDaysUntilWarrantyExpiry(): ?int
    {
        if (!$this->warranty_expiry_date) {
            return null;
        }
        return now()->diffInDays($this->warranty_expiry_date, false);
    }

    public function getAge(): ?int
    {
        if (!$this->purchase_date) {
            return null;
        }
        return $this->purchase_date->diffInYears(now());
    }

    public function getDepreciation(): float
    {
        if (!$this->purchase_price || !$this->lifespan_years || $this->lifespan_years <= 0) {
            return 0;
        }

        $age = $this->getAge() ?? 0;
        $annualDepreciation = $this->purchase_price / $this->lifespan_years;
        $totalDepreciation = min($annualDepreciation * $age, $this->purchase_price);

        return $totalDepreciation;
    }

    public function calculateCurrentValue(): float
    {
        return max(0, ($this->purchase_price ?? 0) - $this->getDepreciation());
    }

    public function getTotalMaintenanceCost(): float
    {
        return $this->maintenances()->sum('total_cost');
    }

    public function moveTo(?int $buildingId, string $reason = ''): EquipmentMovement
    {
        $fromBuilding = $this->building_id;

        $movement = $this->movements()->create([
            'moved_by' => Auth::id(),
            'from_building_id' => $fromBuilding,
            'to_building_id' => $buildingId,
            'movement_type' => $buildingId ? 'transfer' : 'exit',
            'movement_date' => now(),
            'reason' => $reason,
        ]);

        $this->update(['building_id' => $buildingId]);

        return $movement;
    }

    public function putInMaintenance(): void
    {
        $this->update(['status' => 'in_maintenance']);
    }

    public function returnFromMaintenance(?string $condition = null): void
    {
        $this->update([
            'status' => 'in_service',
            'condition' => $condition ?? $this->condition,
            'last_maintenance_date' => now(),
        ]);
    }

    public function dispose(): void
    {
        $this->update([
            'status' => 'disposed',
            'building_id' => null,
        ]);
    }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            'in_service' => 'En service',
            'in_stock' => 'En stock',
            'in_maintenance' => 'En maintenance',
            'out_of_service' => 'Hors service',
            'disposed' => 'Réformé',
            default => $this->status,
        };
    }

    public function getConditionLabel(): string
    {
        return match ($this->condition) {
            'new' => 'Neuf',
            'good' => 'Bon état',
            'fair' => 'État moyen',
            'poor' => 'Mauvais état',
            'broken' => 'Cassé',
            default => $this->condition ?? 'Non défini',
        };
    }

    public function getCategoryLabel(): string
    {
        return match ($this->category) {
            'alimentation' => 'Alimentation',
            'abreuvement' => 'Abreuvement',
            'ventilation' => 'Ventilation',
            'chauffage' => 'Chauffage',
            'eclairage' => 'Éclairage',
            'pesage' => 'Pesage',
            'transport' => 'Transport',
            'nettoyage' => 'Nettoyage',
            'stockage' => 'Stockage',
            'abattage' => 'Abattage',
            'autre' => 'Autre',
            default => $this->category,
        };
    }

    public static function generateInventoryNumber(int $orgId): string
    {
        $count = self::whereHas('farm', fn ($q) => $q->where('organisation_id', $orgId))->count();

        return sprintf('EQP-%05d', $count + 1);
    }
}
