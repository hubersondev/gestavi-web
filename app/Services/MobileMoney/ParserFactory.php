<?php

namespace App\Services\MobileMoney;

use App\Enums\MobileMoneyOperator;
use App\Services\MobileMoney\Contracts\PayloadParser;
use App\Services\MobileMoney\Parsers\MoovMoneyParser;
use App\Services\MobileMoney\Parsers\MtnMomoParser;
use App\Services\MobileMoney\Parsers\OrangeMoneyParser;
use App\Services\MobileMoney\Parsers\WaveParser;

class ParserFactory
{
    public static function for(MobileMoneyOperator $operator): PayloadParser
    {
        return match ($operator) {
            MobileMoneyOperator::Orange => new OrangeMoneyParser,
            MobileMoneyOperator::Mtn => new MtnMomoParser,
            MobileMoneyOperator::Moov => new MoovMoneyParser,
            MobileMoneyOperator::Wave => new WaveParser,
        };
    }
}
