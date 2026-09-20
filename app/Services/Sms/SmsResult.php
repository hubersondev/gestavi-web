<?php

namespace App\Services\Sms;

/**
 * Résultat d'un envoi SMS par un pilote.
 */
final readonly class SmsResult
{
    public function __construct(
        public bool $success,
        public string $driver,
        public ?string $providerId = null,
        public ?string $error = null,
    ) {}

    public static function sent(string $driver, ?string $providerId = null): self
    {
        return new self(true, $driver, $providerId);
    }

    public static function failed(string $driver, string $error): self
    {
        return new self(false, $driver, null, $error);
    }
}
