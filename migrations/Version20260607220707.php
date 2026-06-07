<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260607220707 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE notification (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(255) NOT NULL, message CLOB NOT NULL, type VARCHAR(50) NOT NULL, is_read BOOLEAN NOT NULL, created_at DATETIME NOT NULL, user_id INTEGER NOT NULL, CONSTRAINT FK_BF5476CAA76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_BF5476CAA76ED395 ON notification (user_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__comparable_sale AS SELECT id, insee_code, city, property_type, surface, price, price_per_sqm, sale_date, x, y, source FROM comparable_sale');
        $this->addSql('DROP TABLE comparable_sale');
        $this->addSql('CREATE TABLE comparable_sale (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, insee_code VARCHAR(10) NOT NULL, city VARCHAR(120) DEFAULT NULL, property_type VARCHAR(80) NOT NULL, surface INTEGER NOT NULL, price INTEGER NOT NULL, price_per_sqm INTEGER NOT NULL, sale_date DATE NOT NULL, x DOUBLE PRECISION DEFAULT NULL, y DOUBLE PRECISION DEFAULT NULL, source VARCHAR(50) NOT NULL)');
        $this->addSql('INSERT INTO comparable_sale (id, insee_code, city, property_type, surface, price, price_per_sqm, sale_date, x, y, source) SELECT id, insee_code, city, property_type, surface, price, price_per_sqm, sale_date, x, y, source FROM __temp__comparable_sale');
        $this->addSql('DROP TABLE __temp__comparable_sale');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE notification');
        $this->addSql('CREATE TEMPORARY TABLE __temp__comparable_sale AS SELECT id, insee_code, city, property_type, surface, price, price_per_sqm, sale_date, x, y, source FROM comparable_sale');
        $this->addSql('DROP TABLE comparable_sale');
        $this->addSql('CREATE TABLE comparable_sale (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, insee_code VARCHAR(10) NOT NULL, city VARCHAR(120) DEFAULT NULL, property_type VARCHAR(80) NOT NULL, surface INTEGER NOT NULL, price INTEGER NOT NULL, price_per_sqm INTEGER NOT NULL, sale_date DATE NOT NULL, x DOUBLE PRECISION DEFAULT NULL, y DOUBLE PRECISION DEFAULT NULL, source VARCHAR(50) NOT NULL)');
        $this->addSql('INSERT INTO comparable_sale (id, insee_code, city, property_type, surface, price, price_per_sqm, sale_date, x, y, source) SELECT id, insee_code, city, property_type, surface, price, price_per_sqm, sale_date, x, y, source FROM __temp__comparable_sale');
        $this->addSql('DROP TABLE __temp__comparable_sale');
        $this->addSql('CREATE INDEX idx_comparable_sale_insee_type_date ON comparable_sale (insee_code, property_type, sale_date)');
        $this->addSql('CREATE INDEX idx_comparable_sale_price_sqm ON comparable_sale (price_per_sqm)');
        $this->addSql('CREATE INDEX idx_comparable_sale_search ON comparable_sale (insee_code, property_type, surface, sale_date)');
    }
}
