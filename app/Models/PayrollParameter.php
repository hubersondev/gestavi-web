<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Paramétrage de paie applicable à partir d'une date d'effet.
 * organisation_id NULL = valeurs légales par défaut (Côte d'Ivoire).
 */
class PayrollParameter extends Model
{
    use HasFactory;

    /**
     * Valeurs légales par défaut — Côte d'Ivoire, à compter du 1er janvier 2024.
     *
     * Sources : SMIG 75 000 FCFA (décret 2023), plafond CNPS retraite = 45 × SMIG
     * = 3 375 000 FCFA/mois, prestations familiales et accident du travail sur plafond
     * 70 000 FCFA, barème unique ITS de l'annexe fiscale 2024 appliqué DIRECTEMENT au brut
     * imposable (plus d'abattement de 20 % ni de déduction de la CNPS), prime de transport
     * exonérée jusqu'à 30 000 FCFA, CMU 1 000 FCFA/mois (500 salarié + 500 employeur),
     * FDFP 0,4 % (taxe d'apprentissage) + 0,6 % (formation continue) du brut imposable.
     * Ces montants ont été recoupés sur un bulletin réel de juillet 2026 (brut imposable
     * 407 841 → CNPS 25 694, ITS 61 647, CMU 500, net 350 000). Le taux accident du travail
     * (2 à 5 % selon le secteur) reste à ajuster par organisation.
     *
     * @var array<string, mixed>
     */
    public const LEGAL_DEFAULTS_2024 = [
        'effective_from' => '2024-01-01',
        'cnps_ceiling' => 3_375_000,
        'cnps_employee_rate' => 0.063,
        'cnps_employer_pension_rate' => 0.077,
        'cnps_family_rate' => 0.0575,
        'cnps_work_accident_rate' => 0.02,
        'cnps_family_ceiling' => 70_000,
        'transport_exemption_ceiling' => 30_000,
        'cmu_employee_amount' => 500,
        'cmu_employer_amount' => 500,
        'fdfp_apprenticeship_rate' => 0.004,
        'fdfp_training_rate' => 0.006,
        'its_abatement_rate' => 0.0,
        'family_reductions' => [
            '1' => 0, '1.5' => 5_500, '2' => 11_000, '2.5' => 16_500, '3' => 22_000,
            '3.5' => 27_500, '4' => 33_000, '4.5' => 38_500, '5' => 44_000,
        ],
        'source' => 'Annexe fiscale 2024 / décret SMIG 75 000 FCFA, recoupé sur bulletin réel 07/2026 — taux AT à ajuster par secteur',
        'brackets' => [
            ['lower_bound' => 0, 'upper_bound' => 75_000, 'rate' => 0.00],
            ['lower_bound' => 75_000, 'upper_bound' => 240_000, 'rate' => 0.16],
            ['lower_bound' => 240_000, 'upper_bound' => 800_000, 'rate' => 0.21],
            ['lower_bound' => 800_000, 'upper_bound' => 2_400_000, 'rate' => 0.24],
            ['lower_bound' => 2_400_000, 'upper_bound' => 8_000_000, 'rate' => 0.28],
            ['lower_bound' => 8_000_000, 'upper_bound' => null, 'rate' => 0.32],
        ],
    ];

    protected $fillable = [
        'organisation_id', 'effective_from',
        'cnps_ceiling', 'cnps_employee_rate', 'cnps_employer_pension_rate',
        'cnps_family_rate', 'cnps_work_accident_rate', 'cnps_family_ceiling',
        'transport_exemption_ceiling', 'cmu_employee_amount', 'cmu_employer_amount',
        'fdfp_apprenticeship_rate', 'fdfp_training_rate',
        'its_abatement_rate', 'family_reductions',
        'source', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'cnps_ceiling' => 'decimal:2',
            'cnps_employee_rate' => 'decimal:4',
            'cnps_employer_pension_rate' => 'decimal:4',
            'cnps_family_rate' => 'decimal:4',
            'cnps_work_accident_rate' => 'decimal:4',
            'cnps_family_ceiling' => 'decimal:2',
            'transport_exemption_ceiling' => 'decimal:2',
            'cmu_employee_amount' => 'decimal:2',
            'cmu_employer_amount' => 'decimal:2',
            'fdfp_apprenticeship_rate' => 'decimal:4',
            'fdfp_training_rate' => 'decimal:4',
            'its_abatement_rate' => 'decimal:4',
            'family_reductions' => 'array',
        ];
    }

    // ─── RELATIONS ───────────────────────────────────────────────────────────

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function taxBrackets(): HasMany
    {
        return $this->hasMany(PayrollTaxBracket::class)->orderBy('order');
    }

    public function payrolls(): HasMany
    {
        return $this->hasMany(Payroll::class);
    }

    // ─── SCOPES ──────────────────────────────────────────────────────────────

    public function scopeEffectiveAt(Builder $query, \DateTimeInterface $date): Builder
    {
        return $query->whereDate('effective_from', '<=', $date)->orderByDesc('effective_from');
    }

    // ─── HELPERS ─────────────────────────────────────────────────────────────

    /**
     * Instance NON persistée portant les valeurs légales par défaut (tests, repli sans seeder).
     */
    public static function legalDefaults(): self
    {
        $data = self::LEGAL_DEFAULTS_2024;
        $brackets = $data['brackets'];
        unset($data['brackets']);

        $parameter = new self($data);

        $collection = new Collection;
        foreach ($brackets as $order => $bracket) {
            $collection->push(new PayrollTaxBracket(array_merge($bracket, ['order' => $order])));
        }
        $parameter->setRelation('taxBrackets', $collection);

        return $parameter;
    }

    /**
     * Réduction d'impôt pour charges de famille selon le nombre de parts.
     */
    public function familyReductionFor(float $fiscalParts): float
    {
        $reductions = $this->family_reductions ?? [];
        $key = rtrim(rtrim(number_format($fiscalParts, 1, '.', ''), '0'), '.');

        return (float) ($reductions[$key] ?? 0);
    }

    public function isLegalDefault(): bool
    {
        return $this->organisation_id === null;
    }

    public function getLabel(): string
    {
        return ($this->isLegalDefault() ? 'Barème légal' : 'Paramétrage organisation')
            .' — en vigueur depuis le '.$this->effective_from?->format('d/m/Y');
    }
}
