<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Service;

use App\Application\Service\TokenManager;
use PHPUnit\Framework\TestCase;

final class TokenManagerTest extends TestCase
{
    public function testGenerateRawTokenProducesUrlSafeToken(): void
    {
        $token = (new TokenManager())->generateRawToken();

        self::assertMatchesRegularExpression('/^[A-Za-z0-9\-_]+$/', $token);
        self::assertGreaterThanOrEqual(43, strlen($token));
    }

    public function testHashTokenUsesSha256(): void
    {
        $hash = (new TokenManager())->hashToken('plain-token');

        self::assertSame(hash('sha256', 'plain-token'), $hash);
    }
}
