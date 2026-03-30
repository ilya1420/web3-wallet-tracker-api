<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260330170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add registration context columns to users table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD device_fingerprint VARCHAR(255) DEFAULT NULL, ADD registration_ip VARCHAR(45) DEFAULT NULL, ADD registration_ip_counter_date VARCHAR(10) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP device_fingerprint, DROP registration_ip, DROP registration_ip_counter_date');
    }
}
