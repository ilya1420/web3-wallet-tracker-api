<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260427120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add web3 wallets storage for per-user tracked wallets and on-chain balances.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE web3_wallets (id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid)", user_id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid)", address VARCHAR(42) NOT NULL, rpc_endpoint VARCHAR(255) NOT NULL, network_id VARCHAR(32) NOT NULL, last_known_balance_wei VARCHAR(100) DEFAULT NULL, last_synced_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)", created_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)", updated_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)", UNIQUE INDEX uniq_web3_wallet_user_address_network (user_id, address, network_id), INDEX idx_web3_wallet_user (user_id), INDEX idx_web3_wallet_updated_at (updated_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE web3_wallets ADD CONSTRAINT FK_WEB3_WALLETS_USER FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE web3_wallets DROP FOREIGN KEY FK_WEB3_WALLETS_USER');
        $this->addSql('DROP TABLE web3_wallets');
    }
}
