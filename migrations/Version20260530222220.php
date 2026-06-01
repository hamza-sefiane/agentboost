<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260530222220 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE comparable_sale (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, insee_code VARCHAR(10) NOT NULL, city VARCHAR(120) DEFAULT NULL, property_type VARCHAR(80) NOT NULL, surface INTEGER NOT NULL, price INTEGER NOT NULL, price_per_sqm INTEGER NOT NULL, sale_date DATE NOT NULL, x DOUBLE PRECISION DEFAULT NULL, y DOUBLE PRECISION DEFAULT NULL, source VARCHAR(50) NOT NULL)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE comparable_sale');
    }
}
