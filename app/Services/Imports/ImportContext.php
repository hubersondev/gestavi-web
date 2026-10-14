<?php

namespace App\Services\Imports;

use App\Enums\PaymentMethod;
use App\Models\Account;
use App\Models\Customer;
use App\Models\Farm;
use App\Models\Organisation;
use App\Models\StockCategory;
use App\Models\Supplier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Organisation cible d'un import, utilisateur à l'origine, et résolution des références
 * (fermes, clients, fournisseurs, comptes) par nom ou code, avec cache pour la durée de l'import.
 */
class ImportContext
{
    /** @var array<string, mixed> */
    private array $cache = [];

    public function __construct(
        public readonly Organisation $organisation,
        public readonly ?int $userId = null,
    ) {}

    // ─── Conversions de cellules ────────────────────────────────────────────

    public function string(array $values, string $key): ?string
    {
        $value = $values[$key] ?? null;

        return $value === null ? null : trim((string) $value);
    }

    public function requiredString(array $values, string $key, int $line, string $label): string
    {
        $value = $this->string($values, $key);

        if ($value === null || $value === '') {
            throw new ImportRowException($line, "la colonne « {$label} » est obligatoire.");
        }

        return $value;
    }

    public function number(array $values, string $key, int $line, string $label, ?float $default = null): ?float
    {
        $value = $values[$key] ?? null;

        if ($value === null || $value === '') {
            return $default;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $normalized = str_replace([' ', "\u{a0}", 'XOF', 'FCFA'], '', (string) $value);
        $normalized = str_replace(',', '.', $normalized);

        if (! is_numeric($normalized)) {
            throw new ImportRowException($line, "« {$label} » doit être un nombre (reçu : {$value}).");
        }

        return (float) $normalized;
    }

    public function requiredNumber(array $values, string $key, int $line, string $label): float
    {
        $value = $this->number($values, $key, $line, $label);

        if ($value === null) {
            throw new ImportRowException($line, "la colonne « {$label} » est obligatoire.");
        }

        return $value;
    }

    public function date(array $values, string $key, int $line, string $label): ?Carbon
    {
        $value = $this->string($values, $key);

        if ($value === null || $value === '') {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'Y-m-d H:i:s'] as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);
            } catch (\Throwable) {
                continue;
            }

            if ($date !== false && $date->format($format) === $value) {
                return $date->startOfDay();
            }
        }

        throw new ImportRowException($line, "« {$label} » n'est pas une date valide (attendu JJ/MM/AAAA, reçu : {$value}).");
    }

    public function requiredDate(array $values, string $key, int $line, string $label): Carbon
    {
        $date = $this->date($values, $key, $line, $label);

        if ($date === null) {
            throw new ImportRowException($line, "la colonne « {$label} » est obligatoire.");
        }

        return $date;
    }

    public function bool(array $values, string $key): bool
    {
        $value = Str::lower((string) ($this->string($values, $key) ?? ''));

        return in_array($value, ['oui', 'o', 'yes', 'y', '1', 'true', 'vrai', 'x'], true);
    }

    /**
     * Valeur d'une énumération depuis un libellé français, un slug ou la valeur brute.
     *
     * @param  array<string, string>  $aliases  alias normalisé → valeur
     */
    public function choice(array $values, string $key, int $line, string $label, array $aliases, ?string $default = null): ?string
    {
        $value = $this->string($values, $key);

        if ($value === null || $value === '') {
            return $default;
        }

        $normalized = SpreadsheetReader::normalizeHeader($value);

        if (isset($aliases[$normalized])) {
            return $aliases[$normalized];
        }

        if (in_array($normalized, $aliases, true)) {
            return $normalized;
        }

        throw new ImportRowException($line, sprintf('« %s » : valeur « %s » inconnue. Valeurs admises : %s.', $label, $value, implode(', ', array_unique(array_keys($aliases)))));
    }

    public function paymentMethod(array $values, string $key, int $line): PaymentMethod
    {
        $value = $this->choice($values, $key, $line, 'Mode de paiement', [
            'especes' => 'cash', 'cash' => 'cash', 'caisse' => 'cash',
            'virement' => 'bank_transfer', 'banque' => 'bank_transfer', 'bank_transfer' => 'bank_transfer',
            'cheque' => 'cheque',
            'orange_money' => 'orange_money', 'orange' => 'orange_money',
            'mtn_momo' => 'mtn_momo', 'mtn' => 'mtn_momo', 'momo' => 'mtn_momo',
            'moov_money' => 'moov_money', 'moov' => 'moov_money',
            'wave' => 'wave',
        ], 'cash');

        return PaymentMethod::from($value);
    }

    // ─── Références ─────────────────────────────────────────────────────────

    /**
     * Ferme par nom ou code. Sans valeur : la ferme unique de l'organisation, sinon erreur si requise.
     */
    public function farm(array $values, int $line, bool $required = true, string $key = 'ferme'): ?Farm
    {
        $name = $this->string($values, $key);

        if ($name === null || $name === '') {
            $farms = $this->remember('farms', fn () => Farm::query()->where('organisation_id', $this->organisation->id)->get());

            if ($farms->count() === 1) {
                return $farms->first();
            }

            if ($required) {
                throw new ImportRowException($line, 'la colonne « Ferme » est obligatoire quand l\'organisation a plusieurs fermes.');
            }

            return null;
        }

        $farm = $this->remember('farm:'.Str::lower($name), fn () => Farm::query()
            ->where('organisation_id', $this->organisation->id)
            ->where(fn ($q) => $q->whereRaw('LOWER(name) = ?', [Str::lower($name)])->orWhereRaw('LOWER(code) = ?', [Str::lower($name)]))
            ->first());

        return $farm ?? throw new ImportRowException($line, "ferme « {$name} » introuvable dans l'organisation.");
    }

    public function customer(array $values, int $line, string $key = 'client'): Customer
    {
        $name = $this->requiredString($values, $key, $line, 'Client');

        $customer = $this->remember('customer:'.Str::lower($name), fn () => Customer::query()
            ->where('organisation_id', $this->organisation->id)
            ->where(fn ($q) => $q->whereRaw('LOWER(name) = ?', [Str::lower($name)])->orWhereRaw('LOWER(code) = ?', [Str::lower($name)]))
            ->first());

        return $customer ?? throw new ImportRowException($line, "client « {$name} » introuvable : importez d'abord les clients.");
    }

    /**
     * Fournisseur par nom ou code, créé au niveau de l'organisation s'il n'existe pas.
     */
    public function supplierOrCreate(array $values, int $line, string $key = 'fournisseur'): ?Supplier
    {
        $name = $this->string($values, $key);

        if ($name === null || $name === '') {
            return null;
        }

        return $this->remember('supplier:'.Str::lower($name), fn () => Supplier::query()
            ->where('organisation_id', $this->organisation->id)
            ->where(fn ($q) => $q->whereRaw('LOWER(name) = ?', [Str::lower($name)])->orWhereRaw('LOWER(code) = ?', [Str::lower($name)]))
            ->first() ?? Supplier::create(['organisation_id' => $this->organisation->id, 'name' => $name, 'is_active' => true]));
    }

    public function account(array $values, int $line, string $key = 'compte'): ?Account
    {
        $name = $this->string($values, $key);

        if ($name === null || $name === '') {
            $accounts = $this->remember('accounts', fn () => Account::query()->where('organisation_id', $this->organisation->id)->where('is_active', true)->get());

            return $accounts->firstWhere('is_default', true) ?? ($accounts->count() === 1 ? $accounts->first() : null);
        }

        $account = $this->remember('account:'.Str::lower($name), fn () => Account::query()
            ->where('organisation_id', $this->organisation->id)
            ->where(fn ($q) => $q->whereRaw('LOWER(name) = ?', [Str::lower($name)])->orWhereRaw('LOWER(code) = ?', [Str::lower($name)]))
            ->first());

        return $account ?? throw new ImportRowException($line, "compte de trésorerie « {$name} » introuvable.");
    }

    public function stockCategoryOrCreate(string $name, string $type): StockCategory
    {
        return $this->remember('stock_category:'.Str::lower($name), fn () => StockCategory::query()
            ->where('organisation_id', $this->organisation->id)
            ->whereRaw('LOWER(name) = ?', [Str::lower($name)])
            ->first() ?? StockCategory::create(['organisation_id' => $this->organisation->id, 'name' => $name, 'type' => $type]));
    }

    private function remember(string $key, \Closure $resolver): mixed
    {
        if (! array_key_exists($key, $this->cache)) {
            $this->cache[$key] = $resolver();
        }

        return $this->cache[$key];
    }
}
