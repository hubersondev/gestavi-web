<?php

namespace App\Models;

use App\Models\Concerns\LogsAuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ligne d'écriture : un compte, un débit OU un crédit.
 * Dénormalise organisation, ferme et date pour les états (journal, grand livre, balance).
 */
class JournalLine extends Model
{
    use LogsAuditTrail;

    protected $fillable = [
        'journal_entry_id', 'organisation_id', 'farm_id', 'entry_date',
        'chart_account_code', 'chart_account_label', 'debit', 'credit', 'third_party', 'label', 'position',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
            'position' => 'integer',
        ];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function accountClass(): int
    {
        return ChartAccount::classOf($this->chart_account_code);
    }
}
