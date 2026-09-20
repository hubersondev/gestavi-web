<?php

namespace App\Services\MobileMoney\Parsers;

use App\Enums\MobileMoneyOperator;
use App\Services\MobileMoney\Contracts\PayloadParser;
use App\Services\MobileMoney\Direction;
use App\Services\MobileMoney\MobileMoneyException;
use App\Services\MobileMoney\ParsedTransaction;
use Illuminate\Support\Carbon;

/**
 * Orange Money (Web Payment / notification marchand). Format attendu :
 *
 *  {
 *    "status": "SUCCESS",                 // SUCCESS | FAILED | PENDING
 *    "txnid": "MP240920.1015.A12345",     // identifiant de transaction (obligatoire)
 *    "amount": 25000,                     // montant (obligatoire)
 *    "currency": "XOF",
 *    "customer_msisdn": "2250707070707",  // téléphone du payeur
 *    "customer_name": "Kouamé Jean",
 *    "merchant_msisdn": "2250505050505",  // numéro marchand (résolution de l'organisation)
 *    "order_id": "FAC-2026-00012",        // référence / message
 *    "txn_date": "2026-09-20T10:15:00Z"
 *  }
 */
class OrangeMoneyParser implements PayloadParser
{
    public function parse(array $payload): ParsedTransaction
    {
        $externalId = $payload['txnid'] ?? $payload['transaction_id'] ?? null;
        $amount = $payload['amount'] ?? null;

        if (blank($externalId) || ! is_numeric($amount)) {
            throw MobileMoneyException::invalidPayload('orange', 'txnid et amount sont obligatoires');
        }

        return new ParsedTransaction(
            operator: MobileMoneyOperator::Orange,
            externalId: (string) $externalId,
            amount: (float) $amount,
            occurredAt: isset($payload['txn_date']) ? Carbon::parse($payload['txn_date']) : now(),
            direction: Direction::In,
            currency: strtoupper((string) ($payload['currency'] ?? 'XOF')),
            payerPhone: $payload['customer_msisdn'] ?? null,
            payerName: $payload['customer_name'] ?? null,
            reference: $payload['order_id'] ?? $payload['reference'] ?? null,
            merchantIdentifier: $payload['merchant_msisdn'] ?? null,
            successful: strtoupper((string) ($payload['status'] ?? 'SUCCESS')) === 'SUCCESS',
        );
    }
}
