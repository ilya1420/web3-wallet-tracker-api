<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260601120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create PostgreSQL 18 baseline schema matching the current application model.';
    }

    public function up(Schema $schema): void
    {
        if (!$this->isPostgreSql()) {
            return;
        }

        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS users (
                id UUID NOT NULL,
                email VARCHAR(180) NOT NULL,
                password VARCHAR(255) NOT NULL,
                is_verified BOOLEAN NOT NULL,
                last_login_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                roles JSON NOT NULL,
                CONSTRAINT users_pkey PRIMARY KEY (id),
                CONSTRAINT uniq_user_email UNIQUE (email)
            )
        SQL);
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_user_created_at ON users (created_at)');

        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS login_tokens (
                id UUID NOT NULL,
                email VARCHAR(180) NOT NULL,
                token_hash VARCHAR(64) NOT NULL,
                expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                CONSTRAINT login_tokens_pkey PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_login_token_consume ON login_tokens (email, token_hash, used_at, expires_at)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_login_token_expires_at ON login_tokens (expires_at)');

        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS access_tokens (
                id UUID NOT NULL,
                user_id UUID NOT NULL,
                token_hash VARCHAR(64) NOT NULL,
                expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                CONSTRAINT access_tokens_pkey PRIMARY KEY (id),
                CONSTRAINT uniq_access_token_hash UNIQUE (token_hash),
                CONSTRAINT fk_access_token_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            )
        SQL);
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_access_token_user ON access_tokens (user_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_access_token_expires_at ON access_tokens (expires_at)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_access_token_user_expires_at ON access_tokens (user_id, expires_at)');

        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS user_registration_context (
                user_id UUID NOT NULL,
                device_fingerprint_hash VARCHAR(64) DEFAULT NULL,
                registration_ip_hash VARCHAR(64) DEFAULT NULL,
                registration_ip_counter_date DATE DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                CONSTRAINT user_registration_context_pkey PRIMARY KEY (user_id),
                CONSTRAINT fk_user_registration_context_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
                CONSTRAINT uniq_user_registration_context_fingerprint_hash UNIQUE (device_fingerprint_hash)
            )
        SQL);
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_user_registration_context_ip_date ON user_registration_context (registration_ip_hash, registration_ip_counter_date)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_user_registration_context_updated_at ON user_registration_context (updated_at)');

        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS registration_ip_counters (
                registration_ip_hash VARCHAR(64) NOT NULL,
                counter_date DATE NOT NULL,
                registrations_count INT NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                CONSTRAINT registration_ip_counters_pkey PRIMARY KEY (registration_ip_hash, counter_date)
            )
        SQL);
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_registration_ip_counters_updated_at ON registration_ip_counters (updated_at)');

        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS outbox_messages (
                id UUID NOT NULL,
                body TEXT NOT NULL,
                available_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                locked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                lock_id UUID DEFAULT NULL,
                processed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                CONSTRAINT outbox_messages_pkey PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_outbox_lock_id ON outbox_messages (lock_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_outbox_pending ON outbox_messages (available_at, locked_at, created_at) WHERE processed_at IS NULL');

        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS web3_wallets (
                id UUID NOT NULL,
                user_id UUID NOT NULL,
                address VARCHAR(42) NOT NULL,
                rpc_endpoint VARCHAR(255) NOT NULL,
                network_id VARCHAR(32) NOT NULL,
                last_known_balance_wei VARCHAR(100) DEFAULT NULL,
                last_synced_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                CONSTRAINT web3_wallets_pkey PRIMARY KEY (id),
                CONSTRAINT fk_web3_wallets_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
                CONSTRAINT uniq_web3_wallet_user_address_network UNIQUE (user_id, address, network_id)
            )
        SQL);
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_web3_wallet_user ON web3_wallets (user_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_web3_wallet_updated_at ON web3_wallets (updated_at)');
    }

    public function down(Schema $schema): void
    {
        if (!$this->isPostgreSql()) {
            return;
        }

        $this->addSql('DROP TABLE IF EXISTS web3_wallets');
        $this->addSql('DROP TABLE IF EXISTS user_registration_context');
        $this->addSql('DROP TABLE IF EXISTS outbox_messages');
        $this->addSql('DROP TABLE IF EXISTS registration_ip_counters');
        $this->addSql('DROP TABLE IF EXISTS access_tokens');
        $this->addSql('DROP TABLE IF EXISTS login_tokens');
        $this->addSql('DROP TABLE IF EXISTS users');
    }

    private function isPostgreSql(): bool
    {
        return $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform;
    }
}
