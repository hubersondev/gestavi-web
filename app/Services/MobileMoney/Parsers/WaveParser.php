<?php

namespace App\Services\MobileMoney\Parsers;

use App\Enums\MobileMoneyOperator;
use App\Services\MobileMoney\Contracts\PayloadParser;
use App\Services\MobileMoney\Direction;
use App\Services\MobileMoney\MobileMoneyException;
use App\Services\MobileMoney\ParsedTransaction;
use Illuminate\Support\Carbon;

/**
 * Wave — webhook « checkout.session.completed ». Format attendu :
 *
 *  {
 *    "id": "EV_abc123",
 *    "type": "checkout.session.completed",
 *    "data": {
 *      "id": "cos-18qq25rgr100a",              // identifiant de session
 *      "transaction_id": "T_ABCDEF",            // identifiant de transaction (préféré pour external_id)
 *      "amount": "25000",
 *      "currency": "XOF",
 *      "payment_status": "succeeded",           // succeeded | processing | cancelled
 *      "client_reference": "FAC-2026-00012",
 *      "sender_mobile": "+2250707070707",       // téléphone du payeur
 *      "business_id": "M_xxxxx",                // compte marchand Wave (résolution : accounts.account_number)
 *      "when_completed": "2026-09-20T10:15:00Z"
 *    }
 *  }
 */
class WaveParser implements PayloadParser
{
    public function parse(array $payload): ParsedTransaction
    {
        $data = $payload['data'] ?? $payload;
        $externalId = $data['transaction_id'] ?? $data['id'] ?? null;
        $amount = $data['amount'] ?? null;

        if (blank($externalId) || ! is_numeric($amount)) {
            throw MobileMoneyException::invalidPayload('wave', 'data.transaction_id (ou data.id) et data.amount sont obligatoires');
        }

        $status = strtolower((string) ($data['payment_status'] ?? 'succeeded'));

        return new ParsedTransaction(
            operator: MobileMoneyOperator::Wave,
            externalId: (string) $externalId,
            amount: (float) $amount,
            occurredAt: isset($data['when_completed']) ? Carbon::parse($data['when_completed']) : now(),
            direction: Direction::In,
            currency: strtoupper((string) ($data['currency'] ?? 'XOF')),
            payerPhone: $data['sender_mobile'] ?? null,
            payerName: $data['sender_name'] ?? null,
            reference: $data['client_reference'] ?? null,
            merchantIdentifier: $data['business_id'] ?? null,
            successful: $status === 'succeeded' && (($payload['type'] ?? 'checkout.session.completed') === 'checkout.session.completed'),
        );
    }
}
