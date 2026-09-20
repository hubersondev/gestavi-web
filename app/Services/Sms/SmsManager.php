<?php

namespace App\Services\Sms;

use App\Services\Sms\Contracts\SmsSender;
use App\Services\Sms\Drivers\AfricasTalkingSmsSender;
use App\Services\Sms\Drivers\LogSmsSender;
use App\Services\Sms\Drivers\OrangeSmsSender;
use InvalidArgumentException;

/**
 * Résout le pilote SMS configuré (config/sms.php) et journalise chaque envoi.
 */
class SmsManager
{
    public function driver(?string $name = null): SmsSender
    {
        $name = $name ?? (string) config('sms.default', 'log');
        $config = (array) config("sms.drivers.{$name}", []);

        return match ($name) {
            'log' => new LogSmsSender($config['channel'] ?? null),
            'africastalking' => new AfricasTalkingSmsSender($config),
            'orange' => new OrangeSmsSender($config),
            default => throw new InvalidArgumentException("Pilote SMS inconnu : {$name}"),
        };
    }

    /**
     * Le canal SMS est actif dès qu'un vrai pilote est configuré (ou en test).
     */
    public static function enabled(): bool
    {
        return config('sms.default', 'log') !== 'log' || app()->runningUnitTests();
    }

    /**
     * Envoi immédiat et journalisé (utilisé par le job SendSmsJob).
     */
    public function sendAndLog(SmsMessage $message): SmsResult
    {
        $result = $this->driver($message->driver ?: null)->send($message->to, $message->message);

        $message->forceFill([
            'driver' => $result->driver,
            'status' => $result->success ? SmsMessage::STATUS_SENT : SmsMessage::STATUS_FAILED,
            'provider_id' => $result->providerId,
            'error' => $result->error,
            'sent_at' => $result->success ? now() : null,
        ])->save();

        return $result;
    }
}
