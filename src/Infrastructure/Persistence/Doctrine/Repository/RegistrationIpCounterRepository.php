<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final readonly class RegistrationIpCounterRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function currentCount(string $ipHash, \DateTimeImmutable $date): int
    {
        $count = $this->connection->fetchOne(
            <<<'SQL'
                SELECT registrations_count
                FROM registration_ip_counters
                WHERE registration_ip_hash = :ipHash
                  AND counter_date = :counterDate
            SQL,
            [
                'ipHash' => $ipHash,
                'counterDate' => $date->format('Y-m-d'),
            ],
        );

        return $count === false ? 0 : (int) $count;
    }

    public function reserveSlot(string $ipHash, \DateTimeImmutable $date, int $limit): bool
    {
        $counterDate = $date->format('Y-m-d');
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $current = $this->lockCurrentCount($ipHash, $counterDate);

        if ($current === null) {
            try {
                $this->connection->insert('registration_ip_counters', [
                    'registration_ip_hash' => $ipHash,
                    'counter_date' => $counterDate,
                    'registrations_count' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                return true;
            } catch (UniqueConstraintViolationException) {
                $current = $this->lockCurrentCount($ipHash, $counterDate);
            }
        }

        if ($current === null || $current >= $limit) {
            return false;
        }

        $this->connection->update(
            'registration_ip_counters',
            [
                'registrations_count' => $current + 1,
                'updated_at' => $now,
            ],
            [
                'registration_ip_hash' => $ipHash,
                'counter_date' => $counterDate,
            ],
        );

        return true;
    }

    public function releaseSlot(string $ipHash, \DateTimeImmutable $date): void
    {
        $counterDate = $date->format('Y-m-d');
        $current = $this->lockCurrentCount($ipHash, $counterDate);

        if ($current === null) {
            return;
        }

        if ($current <= 1) {
            $this->connection->delete('registration_ip_counters', [
                'registration_ip_hash' => $ipHash,
                'counter_date' => $counterDate,
            ]);

            return;
        }

        $this->connection->update(
            'registration_ip_counters',
            [
                'registrations_count' => $current - 1,
                'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ],
            [
                'registration_ip_hash' => $ipHash,
                'counter_date' => $counterDate,
            ],
        );
    }

    private function lockCurrentCount(string $ipHash, string $counterDate): ?int
    {
        $count = $this->connection->fetchOne(
            <<<'SQL'
                SELECT registrations_count
                FROM registration_ip_counters
                WHERE registration_ip_hash = :ipHash
                  AND counter_date = :counterDate
                FOR UPDATE
            SQL,
            [
                'ipHash' => $ipHash,
                'counterDate' => $counterDate,
            ],
        );

        return $count === false ? null : (int) $count;
    }
}
