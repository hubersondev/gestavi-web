<?php

namespace App\Models;

use App\Enums\JournalCode;
use App\Models\Concerns\LogsAuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Écriture comptable en partie double (en-tête). Les lignes portent débit/crédit ;
 * Σ débit = Σ crédit est garanti par AccountingService::record().
 * Une écriture ne se modifie ni ne se supprime : elle s'extourne.
 */
class JournalEntry extends Model
{
    use LogsAuditTrail;

    protected $fillable = [
        'organisation_id', 'farm_id', 'fiscal_year_id', 'entry_number', 'entry_date', 'journal', 'label',
        'reference_type', 'reference_id', 'reference_number', 'is_reversal', 'reversed_entry_id', 'is_generated', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'journal' => JournalCode::class,
            'is_reversal' => 'boolean',
            'is_generated' => 'boolean',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class)->orderBy('position');
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function reversedEntry(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_entry_id');
    }

    public function reversal(): HasMany
    {
        return $this->hasMany(self::class, 'reversed_entry_id');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeForPeriod(Builder $query, mixed $from, mixed $to): Builder
    {
        return $query->whereBetween('entry_date', [\Carbon\Carbon::parse($from)->toDateString(), \Carbon\Carbon::parse($to)->toDateString()]);
    }

    public function totalDebit(): float
    {
        return (float) $this->lines()->sum('debit');
    }

    public function totalCredit(): float
    {
        return (float) $this->lines()->sum('credit');
    }

    public function isBalanced(): bool
    {
        return abs($this->totalDebit() - $this->totalCredit()) < 0.01;
    }

    public function isReversed(): bool
    {
        return $this->reversal()->exists();
    }
}
