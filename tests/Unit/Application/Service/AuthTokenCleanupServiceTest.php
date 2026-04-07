<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Service;

use App\Application\Service\AuthTokenCleanupService;
use App\Domain\Entity\AccessToken;
use App\Domain\Entity\LoginToken;
use App\Domain\Repository\AccessTokenRepositoryInterface;
use App\Domain\Repository\LoginTokenRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class AuthTokenCleanupServiceTest extends TestCase
{
    public function testCleanupAggregatesDeletedTokenCounts(): void
    {
        $loginTokenRepository = new class () implements LoginTokenRepositoryInterface {
            public function save(LoginToken $token, bool $flush = true): void
            {
            }

            public function consumeValidToken(string $email, string $tokenHash, \DateTimeImmutable $usedAt): bool
            {
                return false;
            }

            public function deleteObsolete(\DateTimeImmutable $at): int
            {
                return 3;
            }
        };

        $accessTokenRepository = new class () implements AccessTokenRepositoryInterface {
            public function save(AccessToken $token, bool $flush = true): void
            {
            }

            public function findActiveByHash(string $tokenHash, \DateTimeImmutable $at): ?AccessToken
            {
                return null;
            }

            public function deleteExpired(\DateTimeImmutable $at): int
            {
                return 2;
            }
        };

        $result = (new AuthTokenCleanupService($loginTokenRepository, $accessTokenRepository))
            ->cleanup(new \DateTimeImmutable());

        self::assertSame([
            'loginTokens' => 3,
            'accessTokens' => 2,
            'total' => 5,
        ], $result);
    }
}
