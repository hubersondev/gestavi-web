<?php

namespace App\Http\Controllers\Webhooks;

use App\Enums\MobileMoneyOperator;
use App\Http\Controllers\Controller;
use App\Services\MobileMoney\MobileMoneyException;
use App\Services\MobileMoney\MobileMoneyService;
use App\Services\MobileMoney\ParserFactory;
use App\Services\MobileMoney\WebhookSignatureVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * POST /api/webhooks/mobile-money/{operator} — réception des notifications de paiement
 * Orange Money, MTN MoMo, Moov Money et Wave (corps signé HMAC-SHA256).
 */
class MobileMoneyWebhookController extends Controller
{
    public function __invoke(Request $request, string $operator, WebhookSignatureVerifier $verifier, MobileMoneyService $service): JsonResponse
    {
        $enum = MobileMoneyOperator::tryFrom(strtolower($operator));

        if ($enum === null) {
            return response()->json(['message' => 'Opérateur inconnu.'], 404);
        }

        if (! $verifier->verify($request, $enum)) {
            Log::warning('[MobileMoney] Signature de webhook invalide', ['operator' => $enum->value, 'ip' => $request->ip()]);

            return response()->json(['message' => 'Signature invalide.'], 401);
        }

        $payload = $request->json()->all();

        if (! is_array($payload) || $payload === []) {
            return response()->json(['message' => 'Corps JSON attendu.'], 422);
        }

        try {
            $parsed = ParserFactory::for($enum)->parse($payload);

            $organisationId = $request->query('organisation') !== null ? (int) $request->query('organisation') : null;

            [$transaction, $created] = $service->ingest($parsed, $payload, $organisationId);
        } catch (MobileMoneyException $e) {
            Log::warning('[MobileMoney] Notification rejetée', ['operator' => $enum->value, 'error' => $e->getMessage()]);

            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'status' => 'ok',
            'created' => $created,
            'transaction_id' => $transaction->id,
            'transaction_status' => $transaction->status->value,
        ], $created ? 201 : 200);
    }
}
