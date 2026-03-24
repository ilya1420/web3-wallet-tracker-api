<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

final readonly class Email
{
    private string $value;

    public function __construct(string $value)
    {
        $normalized = mb_strtolower(trim($value));

        if (!filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email address.');
        }

        $this->value = $normalized;
    }

    public function value(): string
    {
        return $this->value;
    }
}
