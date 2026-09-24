<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

final readonly class Money
{
    private string $amount;

    public function __construct(string $amount)
    {
        $normalized = trim($amount);

        if (preg_match('/^\d+(?:\.\d+)?$/', $normalized) !== 1) {
            throw new \InvalidArgumentException('Money amount must be a non-negative decimal string.');
        }

        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $whole = ltrim($whole, '0') ?: '0';
        $fraction = rtrim($fraction, '0');
        $this->amount = $fraction === '' ? $whole : sprintf('%s.%s', $whole, $fraction);
    }

    public function amount(): string
    {
        return $this->amount;
    }

    public function isZero(): bool
    {
        return $this->amount === '0';
    }
}
