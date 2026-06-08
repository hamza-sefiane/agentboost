<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260608172240 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__admin_audit_log AS SELECT id, actor_email, target_user_id, "action", ip_address, created_at FROM admin_audit_log');
        $this->addSql('DROP TABLE admin_audit_log');
        $this->addSql('CREATE TABLE admin_audit_log (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, actor_email VARCHAR(180) NOT NULL, target_user_id INTEGER DEFAULT NULL, event VARCHAR(100) NOT NULL, ip_address VARCHAR(45) DEFAULT NULL, created_at DATETIME NOT NULL, CONSTRAINT FK_1F16C5C76C066AFE FOREIGN KEY (target_user_id) REFERENCES app_user (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO admin_audit_log (id, actor_email, target_user_id, event, ip_address, created_at) SELECT id, actor_email, target_user_id, "action", ip_address, created_at FROM __temp__admin_audit_log');
        $this->addSql('DROP TABLE __temp__admin_audit_log');
        $this->addSql('CREATE INDEX IDX_1F16C5C76C066AFE ON admin_audit_log (target_user_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__admin_audit_log AS SELECT id, actor_email, event, ip_address, created_at, target_user_id FROM admin_audit_log');
        $this->addSql('DROP TABLE admin_audit_log');
        $this->addSql('CREATE TABLE admin_audit_log (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, actor_email VARCHAR(180) NOT NULL, "action" VARCHAR(100) NOT NULL, ip_address VARCHAR(45) DEFAULT NULL, created_at DATETIME NOT NULL, target_user_id INTEGER DEFAULT NULL)');
        $this->addSql('INSERT INTO admin_audit_log (id, actor_email, "action", ip_address, created_at, target_user_id) SELECT id, actor_email, event, ip_address, created_at, target_user_id FROM __temp__admin_audit_log');
        $this->addSql('DROP TABLE __temp__admin_audit_log');
    }
}
