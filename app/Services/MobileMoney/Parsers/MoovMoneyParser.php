<?php

namespace App\Services\MobileMoney\Parsers;

use App\Enums\MobileMoneyOperator;
use App\Services\MobileMoney\Contracts\PayloadParser;
use App\Services\MobileMoney\Direction;
use App\Services\MobileMoney\MobileMoneyException;
use App\Services\MobileMoney\ParsedTransaction;
use Illuminate\Support\Carbon;

/**
 * Moov Money (notification marchand). Format attendu :
 *
 *  {
 *    "transaction_id": "MV20260920001",   // obligatoire
 *    "amount": 25000,                      // obligatoire
 *    "currency": "XOF",
 *    "msisdn": "2250101010101",            // téléphone du payeur
 *    "customer_name": "...",
 *    "merchant_msisdn": "2250202020202",   // numéro marchand
 *    "reference": "FAC-2026-00012",
 *    "date": "2026-09-20 10:15:00",
 *    "status": "SUCCESS"                   // SUCCESS | FAILED
 *  }
 */
class MoovMoneyParser implements PayloadParser
{
    public function parse(array $payload): ParsedTransaction
    {
        $externalId = $payload['transaction_id'] ?? $payload['transactionId'] ?? null;
        $amount = $payload['amount'] ?? null;

        if (blank($externalId) || ! is_numeric($amount)) {
            throw MobileMoneyException::invalidPayload('moov', 'transaction_id et amount sont obligatoires');
        }

        return new ParsedTransaction(
            operator: MobileMoneyOperator::Moov,
            externalId: (string) $externalId,
            amount: (float) $amount,
            occurredAt: isset($payload['date']) ? Carbon::parse($payload['date']) : now(),
            direction: Direction::In,
            currency: strtoupper((string) ($payload['currency'] ?? 'XOF')),
            payerPhone: $payload['msisdn'] ?? $payload['customer_msisdn'] ?? null,
            payerName: $payload['customer_name'] ?? null,
            reference: $payload['reference'] ?? null,
            merchantIdentifier: $payload['merchant_msisdn'] ?? null,
            successful: strtoupper((string) ($payload['status'] ?? 'SUCCESS')) === 'SUCCESS',
        );
    }
}
