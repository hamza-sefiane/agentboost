<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260602122017 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE property_photo ADD COLUMN premium_cover BOOLEAN DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__property_photo AS SELECT id, filename, cloudinary_url, cloudinary_public_id, position, created_at, property_id FROM property_photo');
        $this->addSql('DROP TABLE property_photo');
        $this->addSql('CREATE TABLE property_photo (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, filename VARCHAR(255) NOT NULL, cloudinary_url VARCHAR(500) DEFAULT NULL, cloudinary_public_id VARCHAR(255) DEFAULT NULL, position INTEGER NOT NULL, created_at DATETIME NOT NULL, property_id INTEGER NOT NULL, CONSTRAINT FK_D2A44515549213EC FOREIGN KEY (property_id) REFERENCES property (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO property_photo (id, filename, cloudinary_url, cloudinary_public_id, position, created_at, property_id) SELECT id, filename, cloudinary_url, cloudinary_public_id, position, created_at, property_id FROM __temp__property_photo');
        $this->addSql('DROP TABLE __temp__property_photo');
        $this->addSql('CREATE INDEX IDX_D2A44515549213EC ON property_photo (property_id)');
    }
}
