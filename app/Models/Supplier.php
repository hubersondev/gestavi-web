<?php

namespace App\Models;

use App\Models\Concerns\LogsAuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory, LogsAuditTrail, SoftDeletes;

    protected $fillable = [
        'organisation_id',
        'farm_id',
        'parent_supplier_id',
        'name',
        'code',
        'type',
        'contact_name',
        'phone',
        'phone_secondary',
        'email',
        'address',
        'city_id',
        'country_id',
        'tax_id',
        'bank_name',
        'bank_account',
        'payment_terms',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Supplier $supplier) {
            // Dériver organisation_id depuis la ferme si non renseigné
            if ($supplier->organisation_id === null && $supplier->farm_id !== null) {
                $supplier->organisation_id = Farm::find($supplier->farm_id)?->organisation_id;
            }
        });
    }

    // ===== RELATIONS =====

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Fournisseur réseau dont ce fournisseur est une branche locale.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'parent_supplier_id');
    }

    /**
     * Branches locales rattachées à ce fournisseur réseau.
     */
    public function branches(): HasMany
    {
        return $this->hasMany(Supplier::class, 'parent_supplier_id');
    }

    // ===== HELPERS =====

    /**
     * Vrai si ce fournisseur est un réseau (aucune ferme, peut avoir des branches).
     */
    public function isNetwork(): bool
    {
        return $this->farm_id === null && $this->parent_supplier_id === null;
    }

    /**
     * Vrai si ce fournisseur est une branche locale d'un réseau.
     */
    public function isBranch(): bool
    {
        return $this->parent_supplier_id !== null;
    }

    // ===== HELPERS =====

    public function isAlimentSupplier(): bool
    {
        return $this->type === 'aliment';
    }

    public function isMedicamentSupplier(): bool
    {
        return $this->type === 'medicament';
    }

    public function isPoussinSupplier(): bool
    {
        return $this->type === 'poussin';
    }

    public function isMaterielSupplier(): bool
    {
        return $this->type === 'materiel';
    }

    public function isEquipementSupplier(): bool
    {
        return $this->type === 'equipement';
    }

    // ===== SCOPES =====

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeForFarm(Builder $query, int $farmId): Builder
    {
        return $query->where('farm_id', $farmId);
    }

    // ===== HELPERS =====

    /**
     * @return array<string, string>
     */
    public static function typeOptions(): array
    {
        return [
            'aliment' => 'Aliment',
            'medicament' => 'Médicament',
            'poussin' => 'Poussin',
            'materiel' => 'Matériel',
            'equipement' => 'Équipement',
        ];
    }

    public function getTypeLabel(): string
    {
        return self::typeOptions()[$this->type] ?? $this->type;
    }
}
