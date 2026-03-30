<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260330170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Move registration context to dedicated table and optimize auth indexes.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS user_registration_context (user_id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid)", device_fingerprint_hash VARCHAR(64) DEFAULT NULL, registration_ip_hash VARCHAR(64) DEFAULT NULL, registration_ip_counter_date DATE DEFAULT NULL COMMENT "(DC2Type:date_immutable)", created_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)", updated_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)", PRIMARY KEY(user_id), CONSTRAINT FK_USER_REGISTRATION_CONTEXT_USER FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE UNIQUE INDEX uniq_user_registration_context_fingerprint_hash ON user_registration_context (device_fingerprint_hash)');
        $this->addSql('CREATE INDEX idx_user_registration_context_ip_date ON user_registration_context (registration_ip_hash, registration_ip_counter_date)');
        $this->addSql('CREATE INDEX idx_user_registration_context_updated_at ON user_registration_context (updated_at)');

        $this->addSql('ALTER TABLE users DROP COLUMN IF EXISTS device_fingerprint, DROP COLUMN IF EXISTS registration_ip, DROP COLUMN IF EXISTS registration_ip_counter_date');

        $this->addSql('DROP INDEX idx_login_token_lookup ON login_tokens');
        $this->addSql('CREATE INDEX idx_login_token_consume ON login_tokens (email, token_hash, used_at, expires_at)');

        $this->addSql('DROP INDEX idx_access_token_hash ON access_tokens');
        $this->addSql('CREATE INDEX idx_access_token_user_expires_at ON access_tokens (user_id, expires_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_access_token_user_expires_at ON access_tokens');
        $this->addSql('CREATE INDEX idx_access_token_hash ON access_tokens (token_hash)');

        $this->addSql('DROP INDEX idx_login_token_consume ON login_tokens');
        $this->addSql('CREATE INDEX idx_login_token_lookup ON login_tokens (email, token_hash)');

        $this->addSql('ALTER TABLE users ADD device_fingerprint VARCHAR(255) DEFAULT NULL, ADD registration_ip VARCHAR(45) DEFAULT NULL, ADD registration_ip_counter_date VARCHAR(10) DEFAULT NULL');

        $this->addSql('DROP TABLE IF EXISTS user_registration_context');
    }
}
