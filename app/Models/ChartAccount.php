<?php

namespace App\Models;

use App\Enums\ChartAccountType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Compte du plan comptable : plan de référence SYSCOHADA (organisation_id NULL)
 * ou compte propre à une organisation.
 */
class ChartAccount extends Model
{
    public const SOURCE = 'Plan comptable SYSCOHADA révisé (AUDCIF 2017) — à valider par un expert-comptable';

    /**
     * Plan de référence minimal livré avec l'application.
     *
     * @var array<string, array{0: string, 1: ChartAccountType}>
     */
    public const REFERENCE_PLAN = [
        '101' => ['Capital social', ChartAccountType::Equity],
        '104' => ['Compte de l\'exploitant (apports et prélèvements)', ChartAccountType::Equity],
        '121' => ['Report à nouveau créditeur', ChartAccountType::Equity],
        '129' => ['Report à nouveau débiteur', ChartAccountType::Equity],
        '131' => ['Résultat net de l\'exercice', ChartAccountType::Equity],
        '162' => ['Emprunts auprès des établissements de crédit', ChartAccountType::Liability],
        '22' => ['Terrains', ChartAccountType::Asset],
        '23' => ['Bâtiments et installations', ChartAccountType::Asset],
        '242' => ['Matériel et outillage agricole', ChartAccountType::Asset],
        '244' => ['Matériel et mobilier', ChartAccountType::Asset],
        '245' => ['Matériel de transport', ChartAccountType::Asset],
        '28' => ['Amortissements des immobilisations', ChartAccountType::Asset],
        '311' => ['Marchandises (volailles)', ChartAccountType::Asset],
        '321' => ['Matières premières (aliment)', ChartAccountType::Asset],
        '33' => ['Autres approvisionnements (médicaments, litière)', ChartAccountType::Asset],
        '401' => ['Fournisseurs', ChartAccountType::Liability],
        '411' => ['Clients', ChartAccountType::Asset],
        '421' => ['Personnel, rémunérations dues', ChartAccountType::Liability],
        '431' => ['Sécurité sociale (CNPS)', ChartAccountType::Liability],
        '433' => ['Autres organismes sociaux (CMU)', ChartAccountType::Liability],
        '4423' => ['État, taxes FDFP (apprentissage, formation continue)', ChartAccountType::Liability],
        '447' => ['État, impôts retenus à la source (ITS)', ChartAccountType::Liability],
        '4431' => ['État, TVA facturée sur ventes', ChartAccountType::Liability],
        '4452' => ['État, TVA récupérable sur achats', ChartAccountType::Asset],
        '4441' => ['État, impôt sur les bénéfices', ChartAccountType::Liability],
        '471' => ['Débiteurs et créditeurs divers', ChartAccountType::Liability],
        '521' => ['Banques', ChartAccountType::Treasury],
        '531' => ['Chèques postaux', ChartAccountType::Treasury],
        '571' => ['Caisse', ChartAccountType::Treasury],
        '585' => ['Virements de fonds (transferts internes)', ChartAccountType::Treasury],
        '6011' => ['Achats de marchandises (poussins, sujets)', ChartAccountType::Expense],
        '6021' => ['Achats de matières premières (aliment)', ChartAccountType::Expense],
        '6031' => ['Variation des stocks de marchandises', ChartAccountType::Expense],
        '6041' => ['Achats de matières et fournitures (médicaments, litière)', ChartAccountType::Expense],
        '605' => ['Autres achats (fournitures, carburant, eau, électricité)', ChartAccountType::Expense],
        '658' => ['Charges diverses (pertes, vols)', ChartAccountType::Expense],
        '61' => ['Transports', ChartAccountType::Expense],
        '622' => ['Locations et charges locatives', ChartAccountType::Expense],
        '624' => ['Entretien, réparations et maintenance', ChartAccountType::Expense],
        '625' => ['Primes d\'assurance', ChartAccountType::Expense],
        '63' => ['Services extérieurs (honoraires vétérinaires, prestations)', ChartAccountType::Expense],
        '64' => ['Impôts et taxes', ChartAccountType::Expense],
        '6413' => ['Taxes FDFP (apprentissage, formation continue)', ChartAccountType::Expense],
        '661' => ['Rémunérations directes versées au personnel', ChartAccountType::Expense],
        '664' => ['Charges sociales', ChartAccountType::Expense],
        '671' => ['Intérêts des emprunts', ChartAccountType::Expense],
        '681' => ['Dotations aux amortissements d\'exploitation', ChartAccountType::Expense],
        '701' => ['Ventes de marchandises (volailles, œufs)', ChartAccountType::Revenue],
        '7071' => ['Produits accessoires (fumier, sous-produits)', ChartAccountType::Revenue],
        '758' => ['Produits divers', ChartAccountType::Revenue],
        '771' => ['Intérêts de prêts et créances', ChartAccountType::Revenue],
    ];

    protected $fillable = [
        'organisation_id', 'code', 'label', 'class', 'type', 'is_active', 'source',
    ];

    protected function casts(): array
    {
        return [
            'class' => 'integer',
            'type' => ChartAccountType::class,
            'is_active' => 'boolean',
        ];
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /**
     * Comptes visibles par une organisation : les siens puis le plan de référence.
     */
    public function scopeAvailableTo(Builder $query, ?int $organisationId): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('organisation_id')->orWhere('organisation_id', $organisationId));
    }

    /**
     * Libellé d'un code, depuis la base (organisation puis référence) ou le plan embarqué.
     */
    public static function labelFor(string $code, ?int $organisationId = null): string
    {
        $label = static::query()
            ->where('code', $code)
            ->availableTo($organisationId)
            ->orderByRaw('organisation_id IS NULL')
            ->value('label');

        return $label ?? (self::REFERENCE_PLAN[$code][0] ?? "Compte {$code}");
    }

    public static function classOf(string $code): int
    {
        return (int) substr($code, 0, 1);
    }
}
