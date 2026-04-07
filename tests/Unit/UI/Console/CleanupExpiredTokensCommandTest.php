<?php

declare(strict_types=1);

namespace App\Tests\Unit\UI\Console;

use App\Application\Service\AuthTokenCleanupService;
use App\Domain\Entity\AccessToken;
use App\Domain\Entity\LoginToken;
use App\Domain\Repository\AccessTokenRepositoryInterface;
use App\Domain\Repository\LoginTokenRepositoryInterface;
use App\UI\Console\CleanupExpiredTokensCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class CleanupExpiredTokensCommandTest extends TestCase
{
    public function testCommandReportsDeletedTokenCounts(): void
    {
        $cleanupService = new AuthTokenCleanupService(
            new class () implements LoginTokenRepositoryInterface {
                public function save(LoginToken $token, bool $flush = true): void
                {
                }

                public function consumeValidToken(string $email, string $tokenHash, \DateTimeImmutable $usedAt): bool
                {
                    return false;
                }

                public function deleteObsolete(\DateTimeImmutable $at): int
                {
                    return 4;
                }
            },
            new class () implements AccessTokenRepositoryInterface {
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
            },
        );

        $tester = new CommandTester(new CleanupExpiredTokensCommand($cleanupService));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('Deleted 6 token records (4 login tokens, 2 access tokens)', $tester->getDisplay());
    }
}
