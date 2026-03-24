<?php

declare(strict_types=1);

namespace App\Application\DTO;

final readonly class AuthTokenOutput
{
    public function __construct(
        public string $accessToken,
        public string $tokenType,
        public string $expiresAt,
    ) {
    }
}
