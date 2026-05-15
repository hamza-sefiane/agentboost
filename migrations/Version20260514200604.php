<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260514200604 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE app_user (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles CLOB NOT NULL, password VARCHAR(255) NOT NULL, is_verified BOOLEAN NOT NULL, is_active BOOLEAN NOT NULL, next_billing_date DATETIME DEFAULT NULL, subscription_status VARCHAR(20) NOT NULL, cancel_at_period_end BOOLEAN NOT NULL, delete_at_period_end BOOLEAN NOT NULL, current_plan VARCHAR(10) NOT NULL, pending_plan VARCHAR(10) DEFAULT NULL, stripe_customer_id VARCHAR(255) DEFAULT NULL, stripe_subscription_id VARCHAR(255) DEFAULT NULL, company_name VARCHAR(255) DEFAULT NULL, company_address VARCHAR(255) DEFAULT NULL, company_phone VARCHAR(50) DEFAULT NULL, company_logo VARCHAR(255) DEFAULT NULL, agency_street VARCHAR(255) DEFAULT NULL, agency_address_complement VARCHAR(255) DEFAULT NULL, agency_postal_code VARCHAR(20) DEFAULT NULL, agency_city VARCHAR(120) DEFAULT NULL, agency_email VARCHAR(180) DEFAULT NULL, agency_website VARCHAR(255) DEFAULT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_88BDF3E9E7927C74 ON app_user (email)');
        $this->addSql('CREATE TABLE property (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, type VARCHAR(50) NOT NULL, city VARCHAR(255) NOT NULL, postal_code VARCHAR(10) NOT NULL, address VARCHAR(255) DEFAULT NULL, surface INTEGER NOT NULL, rooms INTEGER NOT NULL, parking BOOLEAN NOT NULL, estimate INTEGER DEFAULT NULL, low_estimate INTEGER DEFAULT NULL, high_estimate INTEGER DEFAULT NULL, ad_text CLOB DEFAULT NULL, extra_details CLOB DEFAULT NULL, owner_id INTEGER NOT NULL, CONSTRAINT FK_8BF21CDE7E3C61F9 FOREIGN KEY (owner_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_8BF21CDE7E3C61F9 ON property (owner_id)');
        $this->addSql('CREATE TABLE property_photo (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, filename VARCHAR(255) NOT NULL, position INTEGER NOT NULL, created_at DATETIME NOT NULL, property_id INTEGER NOT NULL, CONSTRAINT FK_D2A44515549213EC FOREIGN KEY (property_id) REFERENCES property (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_D2A44515549213EC ON property_photo (property_id)');
        $this->addSql('CREATE TABLE reset_password_request (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, selector VARCHAR(20) NOT NULL, hashed_token VARCHAR(100) NOT NULL, requested_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, user_id INTEGER NOT NULL, CONSTRAINT FK_7CE748AA76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_7CE748AA76ED395 ON reset_password_request (user_id)');
        $this->addSql('CREATE TABLE stripe_event (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, event_id VARCHAR(255) NOT NULL, payload CLOB NOT NULL, created_at DATETIME NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_110C630A71F7E88B ON stripe_event (event_id)');
        $this->addSql('CREATE TABLE messenger_messages (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, body CLOB NOT NULL, headers CLOB NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL)');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0 ON messenger_messages (queue_name)');
        $this->addSql('CREATE INDEX IDX_75EA56E0E3BD61CE ON messenger_messages (available_at)');
        $this->addSql('CREATE INDEX IDX_75EA56E016BA31DB ON messenger_messages (delivered_at)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE app_user');
        $this->addSql('DROP TABLE property');
        $this->addSql('DROP TABLE property_photo');
        $this->addSql('DROP TABLE reset_password_request');
        $this->addSql('DROP TABLE stripe_event');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
