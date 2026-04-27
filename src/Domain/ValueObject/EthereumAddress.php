<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

final readonly class EthereumAddress
{
    private string $value;

    public function __construct(string $value)
    {
        $normalized = mb_strtolower(trim($value));

        if (preg_match('/^0x[a-f0-9]{40}$/', $normalized) !== 1) {
            throw new \InvalidArgumentException('Ethereum address must match 0x + 40 hex characters.');
        }

        $this->value = $normalized;
    }

    public function value(): string
    {
        return $this->value;
    }
}
