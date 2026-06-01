<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260525133113 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Convert web3_wallets.user_id to Doctrine uuid storage (BINARY(16)) and keep FK compatibility with users.id';
    }

    public function up(Schema $schema): void
    {
        if (!$this->isMySql()) {
            return;
        }

        // For compatibility with mixed environments where previous state may already be CHAR(36).
        $this->addSql('ALTER TABLE web3_wallets DROP FOREIGN KEY FK_WEB3_WALLETS_USER');
        $this->addSql('UPDATE web3_wallets SET user_id = UUID_TO_BIN(user_id, 0) WHERE CHAR_LENGTH(user_id) = 36');
        $this->addSql('ALTER TABLE web3_wallets MODIFY user_id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid)"');
        $this->addSql('ALTER TABLE web3_wallets ADD CONSTRAINT FK_WEB3_WALLETS_USER FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        if (!$this->isMySql()) {
            return;
        }

        $this->addSql('ALTER TABLE web3_wallets DROP FOREIGN KEY FK_WEB3_WALLETS_USER');
        $this->addSql('ALTER TABLE web3_wallets MODIFY user_id CHAR(36) NOT NULL');
        $this->addSql('UPDATE web3_wallets SET user_id = BIN_TO_UUID(user_id, 0)');
        $this->addSql('ALTER TABLE web3_wallets ADD CONSTRAINT FK_WEB3_WALLETS_USER FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
    }

    private function isMySql(): bool
    {
        return $this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform;
    }
}
