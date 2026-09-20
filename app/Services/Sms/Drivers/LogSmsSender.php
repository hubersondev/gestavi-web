<?php

namespace App\Services\Sms\Drivers;

use App\Services\Sms\Contracts\SmsSender;
use App\Services\Sms\SmsResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Pilote de développement : aucun envoi, le SMS est écrit dans le journal.
 */
class LogSmsSender implements SmsSender
{
    public function __construct(private readonly ?string $channel = null) {}

    public function send(string $to, string $message): SmsResult
    {
        $providerId = 'log-'.Str::uuid();

        Log::channel($this->channel)->info('[SMS] Envoi simulé', [
            'to' => $to,
            'message' => $message,
            'provider_id' => $providerId,
        ]);

        return SmsResult::sent($this->name(), $providerId);
    }

    public function name(): string
    {
        return 'log';
    }
}
