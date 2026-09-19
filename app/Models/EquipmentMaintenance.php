<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipmentMaintenance extends Model
{
    use HasFactory;

    protected $fillable = [
        'equipment_id',
        'performed_by',
        'maintenance_type',
        'title',
        'description',
        'labor_cost',
        'parts_cost',
        'total_cost',
        'external_technician',
        'external_company',
        'scheduled_date',
        'performed_date',
        'next_maintenance_date',
        'condition_before',
        'condition_after',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'performed_date' => 'date',
            'next_maintenance_date' => 'date',
            'labor_cost' => 'decimal:2',
            'parts_cost' => 'decimal:2',
            'total_cost' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (EquipmentMaintenance $maintenance) {
            $maintenance->total_cost = ($maintenance->labor_cost ?? 0) + ($maintenance->parts_cost ?? 0);
        });

        static::saved(function (EquipmentMaintenance $maintenance) {
            if ($maintenance->status === 'completed' && $maintenance->next_maintenance_date) {
                $maintenance->equipment()->update([
                    'next_maintenance_date' => $maintenance->next_maintenance_date,
                    'last_maintenance_date' => $maintenance->performed_date ?? now(),
                    'condition' => $maintenance->condition_after,
                ]);
            }
        });
    }

    // ===== RELATIONS =====

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    // ===== SCOPES =====

    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'scheduled')
            ->where('scheduled_date', '<', now());
    }

    public function scopeUpcoming($query, $days = 7)
    {
        return $query->where('status', 'scheduled')
            ->whereBetween('scheduled_date', [now(), now()->addDays($days)]);
    }

    public function scopePreventive($query)
    {
        return $query->where('maintenance_type', 'preventive');
    }

    public function scopeCorrective($query)
    {
        return $query->where('maintenance_type', 'corrective');
    }

    // ===== HELPERS =====

    public function isScheduled(): bool
    {
        return $this->status === 'scheduled';
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isOverdue(): bool
    {
        return $this->isScheduled() && $this->scheduled_date < now();
    }

    public function isPreventive(): bool
    {
        return $this->maintenance_type === 'preventive';
    }

    public function isCorrective(): bool
    {
        return $this->maintenance_type === 'corrective';
    }

    public function start(): void
    {
        $this->update(['status' => 'in_progress']);
        $this->equipment->putInMaintenance();
    }

    public function complete(?string $conditionAfter = null, ?\DateTimeInterface $nextMaintenanceDate = null): void
    {
        $this->update([
            'status' => 'completed',
            'performed_date' => now(),
            'condition_after' => $conditionAfter ?? $this->condition_after,
            'next_maintenance_date' => $nextMaintenanceDate ?? $this->next_maintenance_date,
        ]);

        $this->equipment->returnFromMaintenance($conditionAfter);
    }

    public function cancel(): void
    {
        $this->update(['status' => 'cancelled']);

        if ($this->equipment->isInMaintenance()) {
            $this->equipment->update(['status' => 'in_service']);
        }
    }

    public function getDaysUntilScheduled(): ?int
    {
        if (! $this->scheduled_date) {
            return null;
        }

        return now()->diffInDays($this->scheduled_date, false);
    }

    public function getTypeLabel(): string
    {
        return match ($this->maintenance_type) {
            'preventive' => 'Préventive',
            'corrective' => 'Corrective',
            'inspection' => 'Inspection',
            'cleaning' => 'Nettoyage',
            'calibration' => 'Calibration',
            'replacement' => 'Remplacement',
            default => $this->maintenance_type,
        };
    }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            'scheduled' => 'Planifiée',
            'in_progress' => 'En cours',
            'completed' => 'Terminée',
            'cancelled' => 'Annulée',
            default => $this->status,
        };
    }

    public function getStatusColor(): string
    {
        return match ($this->status) {
            'scheduled' => $this->isOverdue() ? 'danger' : 'warning',
            'in_progress' => 'info',
            'completed' => 'success',
            'cancelled' => 'gray',
            default => 'gray',
        };
    }
}
