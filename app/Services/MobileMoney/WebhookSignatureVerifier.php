<?php

namespace App\Services\MobileMoney;

use App\Enums\MobileMoneyOperator;
use Illuminate\Http\Request;

/**
 * Vérifie la signature HMAC-SHA256 (hexadécimale) du corps brut d'un webhook
 * avec le secret partagé de l'opérateur. En-têtes acceptés : X-Signature,
 * X-Webhook-Signature ; préfixe « sha256= » toléré. Sans secret configuré, refus.
 */
class WebhookSignatureVerifier
{
    public function verify(Request $request, MobileMoneyOperator $operator): bool
    {
        $secret = (string) config("mobilemoney.operators.{$operator->value}.webhook_secret", '');

        if ($secret === '') {
            return false;
        }

        $provided = $request->header('X-Signature') ?? $request->header('X-Webhook-Signature');

        if (blank($provided)) {
            return false;
        }

        $provided = strtolower(trim((string) $provided));

        if (str_starts_with($provided, 'sha256=')) {
            $provided = substr($provided, 7);
        }

        $expected = self::sign($request->getContent(), $secret);

        return hash_equals($expected, $provided);
    }

    public static function sign(string $rawBody, string $secret): string
    {
        return hash_hmac('sha256', $rawBody, $secret);
    }
}
