<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Persistence\Doctrine\Repository;

use App\Infrastructure\Persistence\Doctrine\Repository\OutboxMessageRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use PHPUnit\Framework\TestCase;

final class OutboxMessageRepositoryTest extends TestCase
{
    public function testClaimsPendingBatchWithPostgreSqlSkipLocked(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new PostgreSQLPlatform());
        $connection->expects(self::never())->method('executeStatement');
        $connection
            ->expects(self::once())
            ->method('fetchAllAssociative')
            ->with(
                self::callback(static fn (string $sql): bool => str_contains($sql, 'FOR UPDATE SKIP LOCKED')
                    && str_contains($sql, 'RETURNING outbox.id, outbox.body')
                    && str_contains($sql, 'LIMIT 25')),
                self::callback(static fn (array $params): bool => $params['lockId'] === 'lock-id'
                    && $params['lockedAt'] === '2026-06-01 10:00:00'
                    && $params['availableAt'] === '2026-06-01 10:00:00'
                    && $params['staleBefore'] === '2026-06-01 09:59:00'),
            )
            ->willReturn([
                ['id' => 'message-id', 'body' => '{"type":"send_login_link_email"}'],
            ]);

        $repository = new OutboxMessageRepository($connection);

        self::assertSame(
            [['id' => 'message-id', 'body' => '{"type":"send_login_link_email"}']],
            $repository->claimPendingBatch(
                'lock-id',
                new \DateTimeImmutable('2026-06-01 10:00:00'),
                25,
                new \DateTimeImmutable('2026-06-01 09:59:00'),
            ),
        );
    }
}
