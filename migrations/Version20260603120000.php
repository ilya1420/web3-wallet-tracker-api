<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260603120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Move auth identity to user UUID and add social accounts plus notification channels.';
    }

    public function up(Schema $schema): void
    {
        if (!$this->isPostgreSql()) {
            return;
        }

        $this->addSql('ALTER TABLE users ALTER email DROP NOT NULL');
        $this->addSql('ALTER TABLE users ADD phone VARCHAR(32) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD display_name VARCHAR(120) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_user_phone ON users (phone)');

        $this->addSql('CREATE TABLE social_accounts (id UUID NOT NULL, user_id UUID NOT NULL, provider VARCHAR(32) NOT NULL, provider_user_id VARCHAR(191) NOT NULL, email VARCHAR(180) DEFAULT NULL, raw_profile JSONB NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_social_account_provider_subject ON social_accounts (provider, provider_user_id)');
        $this->addSql('CREATE INDEX idx_social_account_user ON social_accounts (user_id)');
        $this->addSql('ALTER TABLE social_accounts ADD CONSTRAINT FK_1051A533A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE notification_channels (id UUID NOT NULL, user_id UUID NOT NULL, type VARCHAR(32) NOT NULL, destination VARCHAR(191) NOT NULL, verified BOOLEAN NOT NULL, is_primary BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_notification_channel_type_destination ON notification_channels (type, destination)');
        $this->addSql('CREATE INDEX idx_notification_channel_user ON notification_channels (user_id)');
        $this->addSql('CREATE INDEX idx_notification_channel_user_verified ON notification_channels (user_id, verified)');
        $this->addSql('ALTER TABLE notification_channels ADD CONSTRAINT FK_NOTIFICATION_CHANNEL_USER FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        if (!$this->isPostgreSql()) {
            return;
        }

        $this->addSql('ALTER TABLE notification_channels DROP CONSTRAINT FK_NOTIFICATION_CHANNEL_USER');
        $this->addSql('DROP TABLE notification_channels');
        $this->addSql('ALTER TABLE social_accounts DROP CONSTRAINT FK_1051A533A76ED395');
        $this->addSql('DROP TABLE social_accounts');
        $this->addSql('DROP INDEX uniq_user_phone');
        $this->addSql('ALTER TABLE users DROP phone');
        $this->addSql('ALTER TABLE users DROP display_name');
        $this->addSql('ALTER TABLE users ALTER email SET NOT NULL');
    }

    private function isPostgreSql(): bool
    {
        return $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform;
    }
}
