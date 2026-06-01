<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Persistence\Doctrine\Repository;

use App\Infrastructure\Persistence\Doctrine\Repository\RegistrationIpCounterRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use PHPUnit\Framework\TestCase;

final class RegistrationIpCounterRepositoryTest extends TestCase
{
    public function testReservesSlotWithPostgreSqlAtomicUpsert(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new PostgreSQLPlatform());
        $connection
            ->expects(self::once())
            ->method('fetchOne')
            ->with(
                self::callback(static fn (string $sql): bool => str_contains($sql, 'ON CONFLICT (registration_ip_hash, counter_date)')
                    && str_contains($sql, 'registration_ip_counters.registrations_count < :limit')
                    && str_contains($sql, 'RETURNING registrations_count')),
                self::callback(static fn (array $params): bool => $params['ipHash'] === 'ip-hash'
                    && $params['counterDate'] === '2026-06-01'
                    && $params['limit'] === 3
                    && isset($params['now'])),
            )
            ->willReturn(2);

        $repository = new RegistrationIpCounterRepository($connection);

        self::assertTrue($repository->reserveSlot('ip-hash', new \DateTimeImmutable('2026-06-01'), 3));
    }

    public function testRefusesSlotWhenPostgreSqlUpsertReturnsNoRow(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new PostgreSQLPlatform());
        $connection->expects(self::once())->method('fetchOne')->willReturn(false);

        $repository = new RegistrationIpCounterRepository($connection);

        self::assertFalse($repository->reserveSlot('ip-hash', new \DateTimeImmutable('2026-06-01'), 3));
    }

    public function testReleasesSlotWithPostgreSqlSingleStatement(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new PostgreSQLPlatform());
        $connection
            ->expects(self::once())
            ->method('executeStatement')
            ->with(
                self::callback(static fn (string $sql): bool => str_contains($sql, 'WITH decremented AS')
                    && str_contains($sql, 'registrations_count = registrations_count - 1')
                    && str_contains($sql, 'registrations_count <= 1')
                    && str_contains($sql, 'DELETE FROM registration_ip_counters')),
                self::callback(static fn (array $params): bool => $params['ipHash'] === 'ip-hash'
                    && $params['counterDate'] === '2026-06-01'
                    && isset($params['now'])),
            );

        $repository = new RegistrationIpCounterRepository($connection);
        $repository->releaseSlot('ip-hash', new \DateTimeImmutable('2026-06-01'));
    }
}
