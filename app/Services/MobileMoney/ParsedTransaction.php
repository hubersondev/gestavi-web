<?php

namespace App\Services\MobileMoney;

use App\Enums\MobileMoneyOperator;
use Carbon\CarbonInterface;

/**
 * Transaction mobile money normalisée, indépendante du format de l'opérateur.
 *
 * `merchantIdentifier` = numéro marchand (E.164) ou identifiant de compte
 * marchand (business id Wave) permettant de retrouver l'organisation.
 */
final readonly class ParsedTransaction
{
    public function __construct(
        public MobileMoneyOperator $operator,
        public string $externalId,
        public float $amount,
        public CarbonInterface $occurredAt,
        public Direction $direction = Direction::In,
        public string $currency = 'XOF',
        public ?string $payerPhone = null,
        public ?string $payerName = null,
        public ?string $reference = null,
        public ?string $merchantIdentifier = null,
        public bool $successful = true,
    ) {}
}
