<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260607224500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Recreate admin audit log table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE admin_audit_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            actor_email VARCHAR(180) NOT NULL,
            target_user_id INTEGER DEFAULT NULL,
            action VARCHAR(100) NOT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            created_at DATETIME NOT NULL
        )');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE admin_audit_log');
    }
}
