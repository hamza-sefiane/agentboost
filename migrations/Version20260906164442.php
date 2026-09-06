<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260906164442 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Use BIGINT for comparable_sale.price on PostgreSQL';
    }

    public function up(Schema $schema): void
    {
        if (!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            return;
        }

        $this->addSql('ALTER TABLE comparable_sale ALTER COLUMN price TYPE BIGINT');
    }

    public function down(Schema $schema): void
    {
        if (!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            return;
        }

        $this->addSql('ALTER TABLE comparable_sale ALTER COLUMN price TYPE INTEGER');
    }
}
