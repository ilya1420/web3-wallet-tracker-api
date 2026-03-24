<?php

declare(strict_types=1);

namespace App\Application\Service;

final class TokenManager
{
    public function generateRawToken(int $length = 32): string
    {
        return rtrim(strtr(base64_encode(random_bytes($length)), '+/', '-_'), '=');
    }

    public function hashToken(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }
}
