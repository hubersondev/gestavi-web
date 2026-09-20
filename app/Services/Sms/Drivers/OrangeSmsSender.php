<?php

namespace App\Services\Sms\Drivers;

use App\Services\Sms\Contracts\SmsSender;
use App\Services\Sms\SmsResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Orange SMS API (Côte d'Ivoire).
 *
 * 1. POST {base}/oauth/v3/token (Basic client_id:client_secret, grant_type=client_credentials)
 *    → access_token mis en cache jusqu'à expiration.
 * 2. POST {base}/smsmessaging/v1/outbound/tel%3A%2B{sender}/requests
 *    { outboundSMSMessageRequest: { address: "tel:+225…", senderAddress: "tel:+225…", senderName, outboundSMSTextMessage: { message } } }
 *    → resourceURL contenant l'identifiant de la requête.
 *
 * @param  array{client_id: ?string, client_secret: ?string, sender: ?string, sender_name: ?string, base_url: ?string}  $config
 */
class OrangeSmsSender implements SmsSender
{
    public function __construct(private readonly array $config) {}

    public function send(string $to, string $message): SmsResult
    {
        if (blank($this->config['client_id'] ?? null) || blank($this->config['client_secret'] ?? null) || blank($this->config['sender'] ?? null)) {
            return SmsResult::failed($this->name(), 'Identifiants Orange SMS (ORANGE_SMS_CLIENT_ID / SECRET / SENDER) manquants.');
        }

        try {
            $token = $this->accessToken();

            if ($token === null) {
                return SmsResult::failed($this->name(), 'Impossible d\'obtenir un jeton OAuth2 Orange.');
            }

            $sender = ltrim((string) $this->config['sender'], '+');

            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(15)
                ->post($this->baseUrl().'/smsmessaging/v1/outbound/'.rawurlencode('tel:+'.$sender).'/requests', [
                    'outboundSMSMessageRequest' => array_filter([
                        'address' => 'tel:'.$to,
                        'senderAddress' => 'tel:+'.$sender,
                        'senderName' => $this->config['sender_name'] ?? null,
                        'outboundSMSTextMessage' => ['message' => $message],
                    ]),
                ]);

            if ($response->successful()) {
                $resource = (string) $response->json('outboundSMSMessageRequest.resourceURL', '');

                return SmsResult::sent($this->name(), $resource !== '' ? basename($resource) : null);
            }

            return SmsResult::failed($this->name(), $response->json('requestError.serviceException.text') ?? "HTTP {$response->status()}");
        } catch (Throwable $e) {
            return SmsResult::failed($this->name(), $e->getMessage());
        }
    }

    private function accessToken(): ?string
    {
        $cacheKey = 'orange_sms_token_'.md5((string) $this->config['client_id']);

        return Cache::remember($cacheKey, now()->addMinutes(50), function (): ?string {
            $response = Http::withBasicAuth((string) $this->config['client_id'], (string) $this->config['client_secret'])
                ->asForm()
                ->acceptJson()
                ->timeout(15)
                ->post($this->baseUrl().'/oauth/v3/token', ['grant_type' => 'client_credentials']);

            return $response->successful() ? $response->json('access_token') : null;
        });
    }

    private function baseUrl(): string
    {
        return rtrim($this->config['base_url'] ?? 'https://api.orange.com', '/');
    }

    public function name(): string
    {
        return 'orange';
    }
}
