<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Activity;

/**
 * Entrée du journal d'audit (table `activity_log` de Spatie Activitylog), enrichie pour la
 * consultation : organisation, libellés français de l'action et du type d'enregistrement,
 * référence lisible du sujet (même supprimé) et liste des champs modifiés avant / après.
 */
class AuditLog extends Activity
{
    /** Libellés des types d'enregistrements audités. */
    public const SUBJECT_LABELS = [
        Account::class => 'Compte de trésorerie',
        AccountTransfer::class => 'Transfert entre comptes',
        BankReconciliation::class => 'Rapprochement bancaire',
        Contract::class => 'Contrat de travail',
        CreditNote::class => 'Avoir',
        Customer::class => 'Client',
        DailyRecord::class => 'Relevé quotidien',
        Delivery::class => 'Livraison',
        Employee::class => 'Employé',
        Expense::class => 'Dépense',
        Flock::class => 'Bande',
        HealthIncident::class => 'Incident sanitaire',
        JournalEntry::class => 'Écriture comptable',
        JournalLine::class => 'Ligne d\'écriture',
        MobileMoneyTransaction::class => 'Encaissement mobile money',
        Payment::class => 'Paiement',
        Payroll::class => 'Bulletin de paie',
        PurchaseOrder::class => 'Bon de commande',
        Quote::class => 'Devis',
        Sale::class => 'Vente',
        Slaughter::class => 'Abattage',
        Stock::class => 'Stock',
        StockLot::class => 'Lot de stock',
        StockMovement::class => 'Mouvement de stock',
        Supplier::class => 'Fournisseur',
        Transaction::class => 'Transaction de trésorerie',
        Vaccination::class => 'Vaccination',
        User::class => 'Utilisateur',
        Announcement::class => 'Annonce',
        SubscriptionPayment::class => 'Encaissement d\'abonnement',
    ];

    /** Événements de support (hors création / modification / suppression). */
    public const EVENT_LABELS = [
        'impersonation_started' => 'Connexion en tant que',
        'impersonation_ended' => 'Fin de connexion en tant que',
        'password_reset_link_sent' => 'Lien de mot de passe envoyé',
        'two_factor_reset' => 'Double authentification réinitialisée',
        'tokens_revoked' => 'Appareils mobiles déconnectés',
    ];

    /** Attributs qui servent de référence lisible, par ordre de préférence. */
    private const REFERENCE_ATTRIBUTES = ['invoice_number', 'email', 'reference', 'expense_number', 'code', 'employee_number', 'contract_number', 'name', 'title', 'record_date', 'movement_date', 'transaction_date'];

    /** Champs techniques qui n'apportent rien dans un détail de modification. */
    private const HIDDEN_FIELDS = ['id', 'organisation_id', 'farm_id', 'created_at', 'updated_at', 'deleted_at', 'hash', 'previous_hash', 'impersonator_id'];

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function scopeForOrganisation(Builder $query, int $organisationId): Builder
    {
        return $query->where($query->qualifyColumn('organisation_id'), $organisationId);
    }

    public function eventLabel(): string
    {
        return match ($this->event) {
            'created' => 'Création',
            'updated' => 'Modification',
            'deleted' => 'Suppression',
            'restored' => 'Restauration',
            default => self::EVENT_LABELS[$this->event] ?? ucfirst((string) ($this->event ?? $this->description)),
        };
    }

    /** Administrateur de la plateforme qui agissait « en tant que » l'auteur, s'il y en avait un. */
    public function impersonator(): ?User
    {
        $id = $this->getExtraProperty('impersonator_id');

        return $id ? User::query()->find($id) : null;
    }

    public function eventColor(): string
    {
        return match ($this->event) {
            'created' => 'success',
            'updated' => 'warning',
            'deleted' => 'danger',
            'restored' => 'info',
            default => 'gray',
        };
    }

    public function subjectTypeLabel(): string
    {
        return self::SUBJECT_LABELS[$this->subject_type] ?? class_basename((string) $this->subject_type);
    }

    /**
     * Référence lisible du sujet : d'après les attributs journalisés (disponibles même après
     * suppression), sinon le sujet encore présent, sinon son identifiant.
     */
    public function subjectLabel(): string
    {
        $attributes = array_merge(
            (array) $this->getExtraProperty('old', []),
            (array) $this->getExtraProperty('attributes', []),
        );

        foreach (self::REFERENCE_ATTRIBUTES as $attribute) {
            if (filled($attributes[$attribute] ?? null)) {
                return (string) $attributes[$attribute];
            }
        }

        if ($this->relationLoaded('subject') || $this->subject_id) {
            $subject = $this->subject;

            if ($subject) {
                foreach (self::REFERENCE_ATTRIBUTES as $attribute) {
                    if (filled($subject->getAttribute($attribute))) {
                        return (string) $subject->getAttribute($attribute);
                    }
                }
            }
        }

        return '#'.$this->subject_id;
    }

    /**
     * Champs modifiés avec leurs valeurs avant / après (création : après seulement ;
     * suppression : avant seulement), hors champs techniques.
     *
     * @return list<array{field: string, old: mixed, new: mixed}>
     */
    public function diff(): array
    {
        $new = (array) $this->getExtraProperty('attributes', []);
        $old = (array) $this->getExtraProperty('old', []);
        $fields = array_values(array_diff(array_unique(array_merge(array_keys($old), array_keys($new))), self::HIDDEN_FIELDS));

        $rows = [];

        foreach ($fields as $field) {
            $before = $old[$field] ?? null;
            $after = $new[$field] ?? null;

            if ($this->event === 'updated' && $before === $after) {
                continue;
            }

            $rows[] = ['field' => $field, 'old' => $before, 'new' => $after];
        }

        return $rows;
    }

    public function changedFieldsCount(): int
    {
        return count($this->diff());
    }

    public function causerLabel(): string
    {
        $label = $this->causer?->name ?? 'Système';
        $impersonator = $this->impersonator();

        return $impersonator ? $label.' (via '.$impersonator->name.', support)' : $label;
    }

    /** Valeur lisible d'un attribut journalisé. */
    public static function formatValue(mixed $value): string
    {
        return match (true) {
            $value === null, $value === '' => '—',
            is_bool($value) => $value ? 'oui' : 'non',
            is_array($value) => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            is_float($value) || (is_string($value) && is_numeric($value) && str_contains($value, '.')) => rtrim(rtrim(number_format((float) $value, 2, ',', ' '), '0'), ','),
            default => (string) $value,
        };
    }
}
