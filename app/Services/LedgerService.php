<?php

namespace App\Services;

use App\Enums\LedgerAnchorReason;
use App\Exceptions\AccountingException;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\LedgerAnchor;
use App\Models\Organisation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Ledger intangible : chaîne de hachage des écritures comptables, vérification
 * d'intégrité, déverrouillage contrôlé des triggers et ancrage hors base.
 *
 * Chaque écriture porte hash = SHA-256(previous_hash ‖ contenu canonique), où
 * previous_hash est le hash de l'écriture précédente de la même organisation
 * (ordre d'insertion). Toute altération ou suppression casse la chaîne.
 */
class LedgerService
{
    public const string UNLOCK_SETTING = 'gestavi.ledger_unlock';

    private const string ADVISORY_LOCK_NAMESPACE = 'gestavi_ledger';

    private bool $unlocked = false;

    /**
     * Verrouille la chaîne d'une organisation pour la durée de la transaction courante :
     * garantit que deux écritures concurrentes ne partagent pas le même previous_hash.
     */
    public function lockChain(int $organisationId): void
    {
        DB::statement('SELECT pg_advisory_xact_lock(hashtext(?), ?::int)', [self::ADVISORY_LOCK_NAMESPACE, $organisationId]);
    }

    /**
     * Hash de la dernière écriture de l'organisation (null pour une chaîne vide).
     */
    public function headHash(int $organisationId): ?string
    {
        return JournalEntry::query()
            ->where('organisation_id', $organisationId)
            ->orderByDesc('id')
            ->value('hash');
    }

    /**
     * Contenu canonique d'une écriture : champs stables uniquement (jamais l'auteur,
     * qui peut être anonymisé, ni les intitulés de comptes, qui sont des libellés).
     *
     * @param  array<string, mixed>  $entry
     * @param  list<array<string, mixed>>  $lines
     */
    public function canonicalPayload(array $entry, array $lines): string
    {
        $head = implode('|', [
            (string) $entry['organisation_id'],
            (string) ($entry['farm_id'] ?? ''),
            (string) $entry['entry_number'],
            substr((string) $entry['entry_date'], 0, 10),
            $entry['journal'] instanceof \BackedEnum ? $entry['journal']->value : (string) $entry['journal'],
            (string) $entry['label'],
            (string) ($entry['reference_type'] ?? ''),
            (string) ($entry['reference_id'] ?? ''),
            (string) ($entry['reference_number'] ?? ''),
            filter_var($entry['is_reversal'] ?? false, FILTER_VALIDATE_BOOL) ? '1' : '0',
            (string) ($entry['reversed_entry_id'] ?? ''),
        ]);

        $body = array_map(fn (array $line): string => implode('|', [
            (string) $line['code'],
            number_format((float) ($line['debit'] ?? 0), 2, '.', ''),
            number_format((float) ($line['credit'] ?? 0), 2, '.', ''),
            (string) ($line['third_party'] ?? ''),
            (string) ($line['farm_id'] ?? ''),
            (string) ($line['label'] ?? ''),
        ]), $lines);

        return $head."\n".implode("\n", $body);
    }

    public function hashFor(?string $previousHash, string $payload): string
    {
        return hash('sha256', ($previousHash ?? '')."\n".$payload);
    }

    /**
     * Recalcule le hash attendu d'une écriture persistée à partir de ses lignes.
     */
    public function expectedHash(JournalEntry $entry, ?string $previousHash): string
    {
        $lines = $entry->lines()->orderBy('position')->orderBy('id')->get()
            ->map(fn (JournalLine $line): array => [
                'code' => $line->chart_account_code,
                'debit' => $line->debit,
                'credit' => $line->credit,
                'third_party' => $line->third_party,
                'farm_id' => $line->farm_id,
                'label' => $line->label,
            ])->all();

        return $this->hashFor($previousHash, $this->canonicalPayload($entry->getAttributes(), $lines));
    }

    /**
     * Parcourt la chaîne d'une organisation et s'arrête à la première rupture.
     *
     * @return array{valid: bool, checked: int, head_hash: string|null, broken_entry_id: int|null, broken_entry_number: string|null, reason: string|null}
     */
    public function verify(int $organisationId): array
    {
        $checked = 0;
        $previousHash = null;

        foreach (JournalEntry::query()->where('organisation_id', $organisationId)->lazyById(200) as $entry) {
            $failure = match (true) {
                $entry->hash === null => 'missing_hash',
                $entry->previous_hash !== $previousHash => 'chain_break',
                $entry->hash !== $this->expectedHash($entry, $previousHash) => 'hash_mismatch',
                default => null,
            };

            if ($failure !== null) {
                return [
                    'valid' => false,
                    'checked' => $checked,
                    'head_hash' => $previousHash,
                    'broken_entry_id' => $entry->id,
                    'broken_entry_number' => $entry->entry_number,
                    'reason' => $failure,
                ];
            }

            $previousHash = $entry->hash;
            $checked++;
        }

        return ['valid' => true, 'checked' => $checked, 'head_hash' => $previousHash, 'broken_entry_id' => null, 'broken_entry_number' => null, 'reason' => null];
    }

    /**
     * Exécute une opération de maintenance avec les triggers désarmés pour la
     * transaction courante uniquement (SET LOCAL). Réservé à la reconstruction.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function withUnlockedLedger(callable $callback): mixed
    {
        return DB::transaction(function () use ($callback): mixed {
            DB::statement(sprintf("SET LOCAL %s = 'on'", self::UNLOCK_SETTING));
            $this->unlocked = true;

            try {
                $result = $callback();
            } finally {
                $this->unlocked = false;
            }

            // Réarmé explicitement en cas de succès ; un échec annule le SET LOCAL avec la transaction
            DB::statement(sprintf("SET LOCAL %s = 'off'", self::UNLOCK_SETTING));

            return $result;
        });
    }

    /**
     * Vrai pendant une reconstruction : les faits rejoués peuvent dater d'exercices clos.
     */
    public function isUnlocked(): bool
    {
        return $this->unlocked;
    }

    /**
     * Recalcule toute la chaîne d'une organisation (après reconstruction ou pour
     * initialiser des écritures antérieures au ledger). Retourne le nombre d'écritures.
     */
    public function rehash(int $organisationId): int
    {
        return $this->withUnlockedLedger(function () use ($organisationId): int {
            $this->lockChain($organisationId);

            $previousHash = null;
            $count = 0;

            foreach (JournalEntry::query()->where('organisation_id', $organisationId)->lazyById(200) as $entry) {
                $hash = $this->expectedHash($entry, $previousHash);

                JournalEntry::query()->whereKey($entry->id)->update(['previous_hash' => $previousHash, 'hash' => $hash]);

                $previousHash = $hash;
                $count++;
            }

            return $count;
        });
    }

    /**
     * Vérifie la chaîne puis enregistre sa tête en base et dans un fichier hors base.
     * Une chaîne rompue est ancrée comme invalide puis signalée par exception : la
     * trace de la rupture doit survivre à l'exception.
     */
    public function anchor(int $organisationId, LedgerAnchorReason $reason, ?int $userId = null, ?string $notes = null): LedgerAnchor
    {
        $verification = $this->verify($organisationId);

        $head = JournalEntry::query()->where('organisation_id', $organisationId)->orderByDesc('id')->first();

        $anchor = LedgerAnchor::query()->create([
            'organisation_id' => $organisationId,
            'head_entry_id' => $verification['valid'] ? $head?->id : $verification['broken_entry_id'],
            'head_hash' => $verification['valid'] ? $head?->hash : $verification['head_hash'],
            'entry_count' => $verification['checked'],
            'reason' => $reason,
            'is_valid' => $verification['valid'],
            'anchored_by' => $userId,
            'notes' => $verification['valid'] ? $notes : trim(($notes ? $notes.' — ' : '').'rupture sur '.$verification['broken_entry_number'].' ('.$verification['reason'].')'),
        ]);

        $this->writeExternalAnchor($anchor);

        if (! $verification['valid']) {
            throw AccountingException::ledgerCorrupted((string) $verification['broken_entry_number'], (string) $verification['reason']);
        }

        return $anchor;
    }

    /**
     * Ajoute l'ancrage au fichier JSON Lines de l'organisation, hors base de données.
     */
    private function writeExternalAnchor(LedgerAnchor $anchor): void
    {
        $organisation = Organisation::query()->find($anchor->organisation_id);
        $slug = $organisation?->slug ?? 'organisation';
        $path = rtrim((string) config('ledger.anchor_path', 'ledger'), '/').'/'.$anchor->organisation_id.'-'.$slug.'.jsonl';

        $line = json_encode([
            'anchored_at' => $anchor->created_at?->toIso8601String() ?? now()->toIso8601String(),
            'organisation_id' => $anchor->organisation_id,
            'organisation' => $organisation?->name,
            'reason' => $anchor->reason->value,
            'head_entry_id' => $anchor->head_entry_id,
            'head_hash' => $anchor->head_hash,
            'entry_count' => $anchor->entry_count,
            'is_valid' => $anchor->is_valid,
            'anchored_by' => $anchor->anchored_by,
            'notes' => $anchor->notes,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        Storage::disk((string) config('ledger.anchor_disk', 'local'))->append($path, (string) $line);
    }
}
