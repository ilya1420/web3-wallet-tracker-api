<?php

declare(strict_types=1);

namespace App\Application\DTO;

final readonly class ConversionResult
{
    public function __construct(
        public string $from,
        public string $to,
        public string $sourceAmount,
        public string $convertedAmount,
        public string $rate,
        public \DateTimeImmutable $rateUpdatedAt,
    ) {
    }
}
