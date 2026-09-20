<?php

namespace App\Services\MobileMoney\Contracts;

use App\Services\MobileMoney\ParsedTransaction;

interface PayloadParser
{
    /**
     * Transforme le corps JSON décodé d'un webhook en transaction normalisée.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws \App\Services\MobileMoney\MobileMoneyException si un champ obligatoire manque
     */
    public function parse(array $payload): ParsedTransaction;
}
