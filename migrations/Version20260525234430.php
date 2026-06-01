<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260525234430 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE admin_audit_log');
        $this->addSql('CREATE TEMPORARY TABLE __temp__app_user AS SELECT id, email, roles, password, is_verified, is_active, next_billing_date, subscription_status, cancel_at_period_end, delete_at_period_end, current_plan, pending_plan, monthly_estimations, monthly_ai_generations, stripe_customer_id, stripe_subscription_id, company_name, company_address, company_phone, company_logo, agency_street, agency_address_complement, agency_postal_code, agency_city, agency_email, agency_website, created_at FROM app_user');
        $this->addSql('DROP TABLE app_user');
        $this->addSql('CREATE TABLE app_user (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles CLOB NOT NULL, password VARCHAR(255) NOT NULL, is_verified BOOLEAN NOT NULL, is_active BOOLEAN NOT NULL, next_billing_date DATETIME DEFAULT NULL, subscription_status VARCHAR(20) NOT NULL, cancel_at_period_end BOOLEAN NOT NULL, delete_at_period_end BOOLEAN NOT NULL, current_plan VARCHAR(10) NOT NULL, pending_plan VARCHAR(10) DEFAULT NULL, monthly_estimations INTEGER DEFAULT 0 NOT NULL, monthly_ai_generations INTEGER DEFAULT 0 NOT NULL, stripe_customer_id VARCHAR(255) DEFAULT NULL, stripe_subscription_id VARCHAR(255) DEFAULT NULL, company_name VARCHAR(255) DEFAULT NULL, company_address VARCHAR(255) DEFAULT NULL, company_phone VARCHAR(50) DEFAULT NULL, company_logo VARCHAR(255) DEFAULT NULL, agency_street VARCHAR(255) DEFAULT NULL, agency_address_complement VARCHAR(255) DEFAULT NULL, agency_postal_code VARCHAR(20) DEFAULT NULL, agency_city VARCHAR(120) DEFAULT NULL, agency_email VARCHAR(180) DEFAULT NULL, agency_website VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL)');
        $this->addSql('INSERT INTO app_user (id, email, roles, password, is_verified, is_active, next_billing_date, subscription_status, cancel_at_period_end, delete_at_period_end, current_plan, pending_plan, monthly_estimations, monthly_ai_generations, stripe_customer_id, stripe_subscription_id, company_name, company_address, company_phone, company_logo, agency_street, agency_address_complement, agency_postal_code, agency_city, agency_email, agency_website, created_at) SELECT id, email, roles, password, is_verified, is_active, next_billing_date, subscription_status, cancel_at_period_end, delete_at_period_end, current_plan, pending_plan, monthly_estimations, monthly_ai_generations, stripe_customer_id, stripe_subscription_id, company_name, company_address, company_phone, company_logo, agency_street, agency_address_complement, agency_postal_code, agency_city, agency_email, agency_website, created_at FROM __temp__app_user');
        $this->addSql('DROP TABLE __temp__app_user');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_88BDF3E9E7927C74 ON app_user (email)');
        $this->addSql('ALTER TABLE property_photo ADD COLUMN cloudinary_url VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE property_photo ADD COLUMN cloudinary_public_id VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE admin_audit_log (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, actor_email VARCHAR(180) NOT NULL COLLATE "BINARY", target_user_id INTEGER DEFAULT NULL, "action" VARCHAR(100) NOT NULL COLLATE "BINARY", ip_address VARCHAR(45) DEFAULT NULL COLLATE "BINARY", created_at DATETIME NOT NULL)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__app_user AS SELECT id, email, roles, password, is_verified, is_active, created_at, next_billing_date, subscription_status, cancel_at_period_end, delete_at_period_end, current_plan, pending_plan, monthly_estimations, monthly_ai_generations, stripe_customer_id, stripe_subscription_id, company_name, company_address, company_phone, company_logo, agency_street, agency_address_complement, agency_postal_code, agency_city, agency_email, agency_website FROM app_user');
        $this->addSql('DROP TABLE app_user');
        $this->addSql('CREATE TABLE app_user (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles CLOB NOT NULL, password VARCHAR(255) NOT NULL, is_verified BOOLEAN NOT NULL, is_active BOOLEAN NOT NULL, created_at DATETIME DEFAULT \'2026-05-16 00:00:00\' NOT NULL, next_billing_date DATETIME DEFAULT NULL, subscription_status VARCHAR(20) NOT NULL, cancel_at_period_end BOOLEAN NOT NULL, delete_at_period_end BOOLEAN NOT NULL, current_plan VARCHAR(10) NOT NULL, pending_plan VARCHAR(10) DEFAULT NULL, monthly_estimations INTEGER DEFAULT 0 NOT NULL, monthly_ai_generations INTEGER DEFAULT 0 NOT NULL, stripe_customer_id VARCHAR(255) DEFAULT NULL, stripe_subscription_id VARCHAR(255) DEFAULT NULL, company_name VARCHAR(255) DEFAULT NULL, company_address VARCHAR(255) DEFAULT NULL, company_phone VARCHAR(50) DEFAULT NULL, company_logo VARCHAR(255) DEFAULT NULL, agency_street VARCHAR(255) DEFAULT NULL, agency_address_complement VARCHAR(255) DEFAULT NULL, agency_postal_code VARCHAR(20) DEFAULT NULL, agency_city VARCHAR(120) DEFAULT NULL, agency_email VARCHAR(180) DEFAULT NULL, agency_website VARCHAR(255) DEFAULT NULL)');
        $this->addSql('INSERT INTO app_user (id, email, roles, password, is_verified, is_active, created_at, next_billing_date, subscription_status, cancel_at_period_end, delete_at_period_end, current_plan, pending_plan, monthly_estimations, monthly_ai_generations, stripe_customer_id, stripe_subscription_id, company_name, company_address, company_phone, company_logo, agency_street, agency_address_complement, agency_postal_code, agency_city, agency_email, agency_website) SELECT id, email, roles, password, is_verified, is_active, created_at, next_billing_date, subscription_status, cancel_at_period_end, delete_at_period_end, current_plan, pending_plan, monthly_estimations, monthly_ai_generations, stripe_customer_id, stripe_subscription_id, company_name, company_address, company_phone, company_logo, agency_street, agency_address_complement, agency_postal_code, agency_city, agency_email, agency_website FROM __temp__app_user');
        $this->addSql('DROP TABLE __temp__app_user');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_88BDF3E9E7927C74 ON app_user (email)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__property_photo AS SELECT id, filename, position, created_at, property_id FROM property_photo');
        $this->addSql('DROP TABLE property_photo');
        $this->addSql('CREATE TABLE property_photo (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, filename VARCHAR(255) NOT NULL, position INTEGER NOT NULL, created_at DATETIME NOT NULL, property_id INTEGER NOT NULL, CONSTRAINT FK_D2A44515549213EC FOREIGN KEY (property_id) REFERENCES property (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO property_photo (id, filename, position, created_at, property_id) SELECT id, filename, position, created_at, property_id FROM __temp__property_photo');
        $this->addSql('DROP TABLE __temp__property_photo');
        $this->addSql('CREATE INDEX IDX_D2A44515549213EC ON property_photo (property_id)');
    }
}
