<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260516010632 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
{
    $this->addSql("ALTER TABLE app_user ADD COLUMN created_at DATETIME DEFAULT '2026-05-16 00:00:00' NOT NULL");
}

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__app_user AS SELECT id, email, roles, password, is_verified, is_active, next_billing_date, subscription_status, cancel_at_period_end, delete_at_period_end, current_plan, pending_plan, monthly_estimations, monthly_ai_generations, stripe_customer_id, stripe_subscription_id, company_name, company_address, company_phone, company_logo, agency_street, agency_address_complement, agency_postal_code, agency_city, agency_email, agency_website FROM app_user');
        $this->addSql('DROP TABLE app_user');
        $this->addSql('CREATE TABLE app_user (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles CLOB NOT NULL, password VARCHAR(255) NOT NULL, is_verified BOOLEAN NOT NULL, is_active BOOLEAN NOT NULL, next_billing_date DATETIME DEFAULT NULL, subscription_status VARCHAR(20) NOT NULL, cancel_at_period_end BOOLEAN NOT NULL, delete_at_period_end BOOLEAN NOT NULL, current_plan VARCHAR(10) NOT NULL, pending_plan VARCHAR(10) DEFAULT NULL, monthly_estimations INTEGER DEFAULT 0 NOT NULL, monthly_ai_generations INTEGER DEFAULT 0 NOT NULL, stripe_customer_id VARCHAR(255) DEFAULT NULL, stripe_subscription_id VARCHAR(255) DEFAULT NULL, company_name VARCHAR(255) DEFAULT NULL, company_address VARCHAR(255) DEFAULT NULL, company_phone VARCHAR(50) DEFAULT NULL, company_logo VARCHAR(255) DEFAULT NULL, agency_street VARCHAR(255) DEFAULT NULL, agency_address_complement VARCHAR(255) DEFAULT NULL, agency_postal_code VARCHAR(20) DEFAULT NULL, agency_city VARCHAR(120) DEFAULT NULL, agency_email VARCHAR(180) DEFAULT NULL, agency_website VARCHAR(255) DEFAULT NULL)');
        $this->addSql('INSERT INTO app_user (id, email, roles, password, is_verified, is_active, next_billing_date, subscription_status, cancel_at_period_end, delete_at_period_end, current_plan, pending_plan, monthly_estimations, monthly_ai_generations, stripe_customer_id, stripe_subscription_id, company_name, company_address, company_phone, company_logo, agency_street, agency_address_complement, agency_postal_code, agency_city, agency_email, agency_website) SELECT id, email, roles, password, is_verified, is_active, next_billing_date, subscription_status, cancel_at_period_end, delete_at_period_end, current_plan, pending_plan, monthly_estimations, monthly_ai_generations, stripe_customer_id, stripe_subscription_id, company_name, company_address, company_phone, company_logo, agency_street, agency_address_complement, agency_postal_code, agency_city, agency_email, agency_website FROM __temp__app_user');
        $this->addSql('DROP TABLE __temp__app_user');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_88BDF3E9E7927C74 ON app_user (email)');
    }
}
