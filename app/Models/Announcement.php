<?php

namespace App\Models;

use App\Enums\AnnouncementLevel;
use Database\Factories\AnnouncementFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Annonce de la plateforme affichée dans l'espace des organisations (bandeau) et, à la demande,
 * envoyée en notification aux responsables : maintenance programmée, nouveauté, incident.
 * Cible : toutes les organisations, celles d'un plan, ou une seule.
 */
class Announcement extends Model
{
    /** @use HasFactory<AnnouncementFactory> */
    use HasFactory;

    protected $fillable = [
        'title', 'body', 'level', 'starts_at', 'ends_at',
        'plan_id', 'organisation_id', 'is_active', 'notified_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'level' => AnnouncementLevel::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
            'notified_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Annonces actives à cet instant. */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    /** Annonces qui visent une organisation (toutes, son plan, ou elle seule). */
    public function scopeForOrganisation(Builder $query, Organisation $organisation): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->where(fn (Builder $all) => $all->whereNull('plan_id')->whereNull('organisation_id'))
            ->orWhere('organisation_id', $organisation->id)
            ->orWhere(fn (Builder $plan) => $plan->whereNull('organisation_id')->where('plan_id', $organisation->plan_id)));
    }

    /** Organisations visées par l'annonce. */
    public function targetOrganisations(): Builder
    {
        return Organisation::query()
            ->when($this->organisation_id, fn (Builder $q) => $q->whereKey($this->organisation_id))
            ->when(! $this->organisation_id && $this->plan_id, fn (Builder $q) => $q->where('plan_id', $this->plan_id));
    }

    public function targetLabel(): string
    {
        return match (true) {
            $this->organisation_id !== null => 'Structure : '.($this->organisation?->name ?? '#'.$this->organisation_id),
            $this->plan_id !== null => 'Plan : '.($this->plan?->name ?? '#'.$this->plan_id),
            default => 'Toutes les organisations',
        };
    }

    public function isCurrent(): bool
    {
        return $this->is_active
            && $this->starts_at->lessThanOrEqualTo(now())
            && ($this->ends_at === null || $this->ends_at->greaterThanOrEqualTo(now()));
    }
}
