<?php

namespace App\Services\Sms\Drivers;

use App\Services\Sms\Contracts\SmsSender;
use App\Services\Sms\SmsResult;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Africa's Talking — POST /version1/messaging (formulaire, en-tête apiKey).
 * Réponse : SMSMessageData.Recipients[0].{status, messageId}.
 *
 * @param  array{username: ?string, api_key: ?string, sender_id: ?string, base_url: ?string}  $config
 */
class AfricasTalkingSmsSender implements SmsSender
{
    public function __construct(private readonly array $config) {}

    public function send(string $to, string $message): SmsResult
    {
        if (blank($this->config['api_key'] ?? null)) {
            return SmsResult::failed($this->name(), 'AT_API_KEY manquant.');
        }

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->withHeaders(['apiKey' => $this->config['api_key']])
                ->timeout(15)
                ->post(rtrim($this->config['base_url'] ?? 'https://api.africastalking.com', '/').'/version1/messaging', array_filter([
                    'username' => $this->config['username'] ?? 'sandbox',
                    'to' => $to,
                    'message' => $message,
                    'from' => $this->config['sender_id'] ?? null,
                ]));

            $recipient = $response->json('SMSMessageData.Recipients.0');

            if ($response->successful() && in_array($recipient['status'] ?? null, ['Success', 'Sent', 'Queued'], true)) {
                return SmsResult::sent($this->name(), $recipient['messageId'] ?? null);
            }

            return SmsResult::failed($this->name(), $recipient['status'] ?? $response->json('SMSMessageData.Message') ?? "HTTP {$response->status()}");
        } catch (Throwable $e) {
            return SmsResult::failed($this->name(), $e->getMessage());
        }
    }

    public function name(): string
    {
        return 'africastalking';
    }
}
