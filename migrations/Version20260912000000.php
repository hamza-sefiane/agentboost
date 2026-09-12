<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260912000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the database-backed Symfony sessions table';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof PostgreSQLPlatform) {
            $this->addSql('CREATE TABLE IF NOT EXISTS sessions (sess_id VARCHAR(128) NOT NULL, sess_data BYTEA NOT NULL, sess_lifetime INTEGER NOT NULL, sess_time INTEGER NOT NULL, PRIMARY KEY (sess_id))');
            $this->addSql('CREATE INDEX IF NOT EXISTS IDX_SESSIONS_LIFETIME ON sessions (sess_lifetime)');

            return;
        }

        if ($platform instanceof SQLitePlatform) {
            $this->addSql('CREATE TABLE IF NOT EXISTS sessions (sess_id VARCHAR(128) NOT NULL PRIMARY KEY, sess_data BLOB NOT NULL, sess_lifetime INTEGER NOT NULL, sess_time INTEGER NOT NULL)');
            $this->addSql('CREATE INDEX IF NOT EXISTS IDX_SESSIONS_LIFETIME ON sessions (sess_lifetime)');

            return;
        }

        $this->abortIf(true, sprintf('Unsupported database platform for sessions: %s', $platform::class));
    }

    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();

        $this->abortIf(
            !$platform instanceof PostgreSQLPlatform && !$platform instanceof SQLitePlatform,
            sprintf('Unsupported database platform for sessions: %s', $platform::class),
        );

        $this->addSql('DROP TABLE sessions');
    }
}
