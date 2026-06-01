<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260601121000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Repair MySQL outbox and registration counter tables removed by an earlier generated migration.';
    }

    public function up(Schema $schema): void
    {
        if (!$this->isMySql()) {
            return;
        }

        $this->addSql('CREATE TABLE IF NOT EXISTS registration_ip_counters (registration_ip_hash VARCHAR(64) NOT NULL, counter_date DATE NOT NULL COMMENT "(DC2Type:date_immutable)", registrations_count INT NOT NULL, created_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)", updated_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)", INDEX idx_registration_ip_counters_updated_at (updated_at), PRIMARY KEY(registration_ip_hash, counter_date)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE IF NOT EXISTS outbox_messages (id CHAR(36) NOT NULL, body LONGTEXT NOT NULL, available_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)", locked_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)", lock_id CHAR(36) DEFAULT NULL, processed_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)", created_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)", INDEX idx_outbox_lock_id (lock_id), INDEX idx_outbox_pending (processed_at, available_at, locked_at, created_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        if (!$this->isMySql()) {
            return;
        }

        // Intentionally no-op: this is a data-safety repair migration.
    }

    private function isMySql(): bool
    {
        return $this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform;
    }
}
