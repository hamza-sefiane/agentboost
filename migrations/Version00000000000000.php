<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version00000000000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial schema (manually aligned): users, subscriptions and properties';
    }

    public function up(Schema $schema): void
    {
        // =========================
        // USER
        // =========================
        $this->addSql('
            CREATE TABLE `user` (
                id INT AUTO_INCREMENT NOT NULL,
                email VARCHAR(180) NOT NULL,
                roles JSON NOT NULL,
                password VARCHAR(255) NOT NULL,

                is_active TINYINT(1) NOT NULL DEFAULT 0,
                next_billing_date DATETIME DEFAULT NULL,
                subscription_status VARCHAR(20) NOT NULL DEFAULT \'inactive\',
                cancel_at_period_end TINYINT(1) NOT NULL DEFAULT 0,

                current_plan VARCHAR(10) NOT NULL DEFAULT \'monthly\',
                pending_plan VARCHAR(10) DEFAULT NULL,

                stripe_customer_id VARCHAR(255) DEFAULT NULL,
                stripe_subscription_id VARCHAR(255) DEFAULT NULL,

                UNIQUE INDEX UNIQ_USER_EMAIL (email),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ');

        // =========================
        // PROPERTY
        // =========================
        $this->addSql('
            CREATE TABLE property (
                id INT AUTO_INCREMENT NOT NULL,
                owner_id INT NOT NULL,
                type VARCHAR(50) NOT NULL,
                city VARCHAR(255) NOT NULL,
                postal_code VARCHAR(10) NOT NULL,
                surface INT NOT NULL,
                rooms INT NOT NULL,
                estimate INT DEFAULT NULL,
                ad_text LONGTEXT DEFAULT NULL,
                INDEX IDX_PROPERTY_OWNER (owner_id),
                PRIMARY KEY(id),
                CONSTRAINT FK_PROPERTY_OWNER
                    FOREIGN KEY (owner_id)
                    REFERENCES `user` (id)
                    ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE property');
        $this->addSql('DROP TABLE `user`');
    }
}
