<?php

namespace App\Services\MobileMoney\Parsers;

use App\Enums\MobileMoneyOperator;
use App\Services\MobileMoney\Contracts\PayloadParser;
use App\Services\MobileMoney\Direction;
use App\Services\MobileMoney\MobileMoneyException;
use App\Services\MobileMoney\ParsedTransaction;
use Illuminate\Support\Carbon;

/**
 * MTN MoMo — callback « Request to Pay » (Collections API). Format attendu :
 *
 *  {
 *    "financialTransactionId": "1234567890",   // identifiant MTN (obligatoire, sinon externalId)
 *    "externalId": "FAC-2026-00012",           // référence marchande
 *    "amount": "25000",
 *    "currency": "XOF",
 *    "payer": { "partyIdType": "MSISDN", "partyId": "2250505050505" },
 *    "payerMessage": "Paiement facture",
 *    "payeeNote": "...",
 *    "status": "SUCCESSFUL",                   // SUCCESSFUL | FAILED | PENDING
 *    "payee": { "partyIdType": "MSISDN", "partyId": "2250101010101" },  // numéro marchand (optionnel)
 *    "createdAt": "2026-09-20T10:15:00Z"       // optionnel
 *  }
 */
class MtnMomoParser implements PayloadParser
{
    public function parse(array $payload): ParsedTransaction
    {
        $externalId = $payload['financialTransactionId'] ?? $payload['externalId'] ?? null;
        $amount = $payload['amount'] ?? null;

        if (blank($externalId) || ! is_numeric($amount)) {
            throw MobileMoneyException::invalidPayload('mtn', 'financialTransactionId et amount sont obligatoires');
        }

        return new ParsedTransaction(
            operator: MobileMoneyOperator::Mtn,
            externalId: (string) $externalId,
            amount: (float) $amount,
            occurredAt: isset($payload['createdAt']) ? Carbon::parse($payload['createdAt']) : now(),
            direction: Direction::In,
            currency: strtoupper((string) ($payload['currency'] ?? 'XOF')),
            payerPhone: data_get($payload, 'payer.partyId'),
            payerName: $payload['payerName'] ?? null,
            reference: $payload['externalId'] ?? $payload['payerMessage'] ?? null,
            merchantIdentifier: data_get($payload, 'payee.partyId'),
            successful: strtoupper((string) ($payload['status'] ?? 'SUCCESSFUL')) === 'SUCCESSFUL',
        );
    }
}
