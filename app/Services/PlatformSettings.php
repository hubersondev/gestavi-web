<?php

namespace App\Services;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Réglages de la plateforme réglables depuis le panel Admin, sans redéploiement : identité de
 * l'éditeur (en-tête des factures d'abonnement), contact support affiché aux organisations,
 * délai de grâce des abonnements. Les secrets (SMS, météo, mobile money, MinIO) restent dans
 * l'environnement ; l'interface n'en montre que l'état.
 */
class PlatformSettings
{
    private const CACHE_KEY = 'gestavi.platform_settings';

    /** Valeurs par défaut (et liste des clés connues). */
    public const DEFAULTS = [
        'company_name' => 'GESTAVI',
        'company_address' => null,
        'company_tax_id' => null,
        'company_email' => null,
        'company_phone' => null,
        'support_email' => 'support@gestavi.com',
        'support_phone' => null,
        'subscription_grace_days' => null,
    ];

    /**
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        $stored = Cache::driver('array')->rememberForever(self::CACHE_KEY, fn (): array => PlatformSetting::query()->pluck('value', 'key')->map(fn ($v) => is_array($v) ? ($v['v'] ?? null) : $v)->all());

        return array_merge(self::DEFAULTS, array_filter($stored, fn ($v): bool => $v !== null && $v !== ''));
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all()[$key] ?? $default ?? self::DEFAULTS[$key] ?? null;
    }

    public static function set(string $key, mixed $value, ?int $userId = null): void
    {
        PlatformSetting::query()->updateOrCreate(['key' => $key], ['value' => ['v' => $value], 'updated_by' => $userId]);
        Cache::driver('array')->forget(self::CACHE_KEY);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public static function setMany(array $values, ?int $userId = null): void
    {
        foreach ($values as $key => $value) {
            if (array_key_exists($key, self::DEFAULTS)) {
                self::set($key, $value, $userId);
            }
        }
    }

    public static function supportEmail(): string
    {
        return (string) self::get('support_email');
    }

    public static function companyName(): string
    {
        return (string) self::get('company_name');
    }

    public static function flushCache(): void
    {
        Cache::driver('array')->forget(self::CACHE_KEY);
    }
}
