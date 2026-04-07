<?php

declare(strict_types=1);

namespace App\Tests\Integration\Support;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;

final class DatabaseResetter
{
    public static function reset(EntityManagerInterface $entityManager): void
    {
        $connection = $entityManager->getConnection();
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($entityManager);

        $connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');

        try {
            self::dropSqlTables($connection);

            if ($metadata !== []) {
                $schemaTool->dropSchema($metadata);
                $schemaTool->createSchema($metadata);
            }

            self::createSqlTables($connection);
        } finally {
            $connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        }

        $entityManager->clear();
    }

    private static function dropSqlTables(Connection $connection): void
    {
        $connection->executeStatement('DROP TABLE IF EXISTS outbox_messages');
        $connection->executeStatement('DROP TABLE IF EXISTS registration_ip_counters');
    }

    private static function createSqlTables(Connection $connection): void
    {
        $connection->executeStatement(
            <<<'SQL'
                CREATE TABLE registration_ip_counters (
                    registration_ip_hash VARCHAR(64) NOT NULL,
                    counter_date DATE NOT NULL,
                    registrations_count INT NOT NULL,
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL,
                    PRIMARY KEY(registration_ip_hash, counter_date),
                    INDEX idx_registration_ip_counters_updated_at (updated_at)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL,
        );

        $connection->executeStatement(
            <<<'SQL'
                CREATE TABLE outbox_messages (
                    id CHAR(36) NOT NULL,
                    body LONGTEXT NOT NULL,
                    available_at DATETIME NOT NULL,
                    locked_at DATETIME DEFAULT NULL,
                    lock_id CHAR(36) DEFAULT NULL,
                    processed_at DATETIME DEFAULT NULL,
                    created_at DATETIME NOT NULL,
                    PRIMARY KEY(id),
                    INDEX idx_outbox_pending (processed_at, available_at, locked_at, created_at),
                    INDEX idx_outbox_lock_id (lock_id)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL,
        );
    }
}
