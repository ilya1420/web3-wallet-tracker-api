<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\Migrations\AbstractMigration;

final class Version20260324120500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create users, login_tokens and access_tokens tables.';
    }

    public function up(Schema $schema): void
    {
        if (!$this->isMySql()) {
            return;
        }

        $this->addSql('CREATE TABLE users (id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid)", email VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, is_verified TINYINT(1) NOT NULL, last_login_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)", created_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)", roles JSON NOT NULL, UNIQUE INDEX uniq_user_email (email), INDEX idx_user_created_at (created_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE login_tokens (id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid)", email VARCHAR(180) NOT NULL, token_hash VARCHAR(64) NOT NULL, expires_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)", used_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)", created_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)", INDEX idx_login_token_lookup (email, token_hash), INDEX idx_login_token_expires_at (expires_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE access_tokens (id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid)", user_id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid)", token_hash VARCHAR(64) NOT NULL, expires_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)", created_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)", UNIQUE INDEX UNIQ_ACCESS_TOKEN_HASH (token_hash), INDEX IDX_ACCESS_TOKEN_USER (user_id), INDEX idx_access_token_hash (token_hash), INDEX idx_access_token_expires_at (expires_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE access_tokens ADD CONSTRAINT FK_ACCESS_TOKEN_USER FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        if (!$this->isMySql()) {
            return;
        }

        $this->addSql('ALTER TABLE access_tokens DROP FOREIGN KEY FK_ACCESS_TOKEN_USER');
        $this->addSql('DROP TABLE access_tokens');
        $this->addSql('DROP TABLE login_tokens');
        $this->addSql('DROP TABLE users');
    }

    private function isMySql(): bool
    {
        return $this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform;
    }
}
