<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class OutboxMessageRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function add(string $body, \DateTimeImmutable $availableAt): void
    {
        $now = new \DateTimeImmutable();

        $this->connection->insert('outbox_messages', [
            'id' => Uuid::v7()->toRfc4122(),
            'body' => $body,
            'available_at' => $availableAt->format('Y-m-d H:i:s'),
            'locked_at' => null,
            'lock_id' => null,
            'processed_at' => null,
            'created_at' => $now->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return list<array{id:string,body:string}>
     */
    public function claimPendingBatch(
        string $lockId,
        \DateTimeImmutable $now,
        int $limit,
        \DateTimeImmutable $staleBefore,
    ): array {
        $sql = <<<'SQL'
            UPDATE outbox_messages
            SET lock_id = :lockId,
                locked_at = :lockedAt
            WHERE processed_at IS NULL
              AND available_at <= :availableAt
              AND (lock_id IS NULL OR locked_at < :staleBefore)
            ORDER BY created_at ASC
            LIMIT %d
        SQL;

        $this->connection->executeStatement(
            sprintf($sql, max(1, $limit)),
            [
                'lockId' => $lockId,
                'lockedAt' => $now->format('Y-m-d H:i:s'),
                'availableAt' => $now->format('Y-m-d H:i:s'),
                'staleBefore' => $staleBefore->format('Y-m-d H:i:s'),
            ],
        );

        /** @var list<array{id:string,body:string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT id, body
                FROM outbox_messages
                WHERE lock_id = :lockId
                  AND processed_at IS NULL
                ORDER BY created_at ASC
            SQL,
            ['lockId' => $lockId],
        );

        return $rows;
    }

    public function markProcessed(string $messageId, \DateTimeImmutable $processedAt): void
    {
        $this->connection->update(
            'outbox_messages',
            [
                'processed_at' => $processedAt->format('Y-m-d H:i:s'),
                'locked_at' => null,
                'lock_id' => null,
            ],
            ['id' => $messageId],
        );
    }

    public function release(string $messageId): void
    {
        $this->connection->update(
            'outbox_messages',
            [
                'locked_at' => null,
                'lock_id' => null,
            ],
            ['id' => $messageId],
        );
    }
}
