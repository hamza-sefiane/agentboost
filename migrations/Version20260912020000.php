<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260912020000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the preferred locale to existing and future users';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof PostgreSQLPlatform) {
            $this->addSql("ALTER TABLE app_user ADD COLUMN IF NOT EXISTS locale VARCHAR(5) DEFAULT 'fr' NOT NULL");

            return;
        }

        if ($platform instanceof SQLitePlatform) {
            $this->addSql("ALTER TABLE app_user ADD COLUMN locale VARCHAR(5) DEFAULT 'fr' NOT NULL");

            return;
        }

        $this->abortIf(true, 'This migration supports PostgreSQL and SQLite only.');
    }

    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof PostgreSQLPlatform || $platform instanceof SQLitePlatform) {
            $this->addSql('ALTER TABLE app_user DROP COLUMN locale');

            return;
        }

        $this->abortIf(true, 'This migration supports PostgreSQL and SQLite only.');
    }
}
