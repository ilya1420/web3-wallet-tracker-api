<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

final readonly class Currency
{
    private string $code;

    public function __construct(string $code)
    {
        $normalized = strtoupper(trim($code));

        if (preg_match('/^[A-Z0-9$@-]{2,16}$/', $normalized) !== 1) {
            throw new \InvalidArgumentException('Currency code must contain 2 to 16 supported symbol characters.');
        }

        $this->code = $normalized;
    }

    public function code(): string
    {
        return $this->code;
    }
}
