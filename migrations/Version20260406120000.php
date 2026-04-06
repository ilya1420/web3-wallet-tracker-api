<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260406120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add transactional outbox and registration IP counters for consistent high-load registration flow.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE registration_ip_counters (registration_ip_hash VARCHAR(64) NOT NULL, counter_date DATE NOT NULL COMMENT "(DC2Type:date_immutable)", registrations_count INT NOT NULL, created_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)", updated_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)", PRIMARY KEY(registration_ip_hash, counter_date)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE INDEX idx_registration_ip_counters_updated_at ON registration_ip_counters (updated_at)');
        $this->addSql('CREATE TABLE outbox_messages (id CHAR(36) NOT NULL, body LONGTEXT NOT NULL, available_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)", locked_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)", lock_id CHAR(36) DEFAULT NULL, processed_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)", created_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)", PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE INDEX idx_outbox_pending ON outbox_messages (processed_at, available_at, locked_at, created_at)');
        $this->addSql('CREATE INDEX idx_outbox_lock_id ON outbox_messages (lock_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE outbox_messages');
        $this->addSql('DROP TABLE registration_ip_counters');
    }
}
