<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\DTO\ConversionResult;
use App\Domain\ValueObject\Currency;
use App\Domain\ValueObject\Money;

interface CurrencyConverterInterface
{
    public function convert(Money $amount, Currency $from, Currency $to): ConversionResult;
}
