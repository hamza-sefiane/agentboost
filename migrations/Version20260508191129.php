<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260508191129 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user ADD agency_street VARCHAR(255) DEFAULT NULL, ADD agency_address_complement VARCHAR(255) DEFAULT NULL, ADD agency_postal_code VARCHAR(20) DEFAULT NULL, ADD agency_city VARCHAR(120) DEFAULT NULL, ADD agency_email VARCHAR(180) DEFAULT NULL, ADD agency_website VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user DROP agency_street, DROP agency_address_complement, DROP agency_postal_code, DROP agency_city, DROP agency_email, DROP agency_website');
    }
}
