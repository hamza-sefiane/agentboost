<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260506231632 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE property_photo (id INT AUTO_INCREMENT NOT NULL, filename VARCHAR(255) NOT NULL, position INT DEFAULT NULL, created_at DATETIME NOT NULL, property_id INT NOT NULL, INDEX IDX_D2A44515549213EC (property_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE property_photo ADD CONSTRAINT FK_D2A44515549213EC FOREIGN KEY (property_id) REFERENCES property (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE property DROP FOREIGN KEY `FK_8BF21CDE7E3C61F9`');
        $this->addSql('DROP INDEX fk_8bf21cde7e3c61f9 ON property');
        $this->addSql('CREATE INDEX IDX_8BF21CDE7E3C61F9 ON property (owner_id)');
        $this->addSql('ALTER TABLE property ADD CONSTRAINT `FK_8BF21CDE7E3C61F9` FOREIGN KEY (owner_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE property_photo DROP FOREIGN KEY FK_D2A44515549213EC');
        $this->addSql('DROP TABLE property_photo');
        $this->addSql('ALTER TABLE property DROP FOREIGN KEY FK_8BF21CDE7E3C61F9');
        $this->addSql('DROP INDEX idx_8bf21cde7e3c61f9 ON property');
        $this->addSql('CREATE INDEX FK_8BF21CDE7E3C61F9 ON property (owner_id)');
        $this->addSql('ALTER TABLE property ADD CONSTRAINT FK_8BF21CDE7E3C61F9 FOREIGN KEY (owner_id) REFERENCES user (id) ON DELETE CASCADE');
    }
}
