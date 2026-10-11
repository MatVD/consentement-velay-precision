<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261011191500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add optional client representative fields without changing existing identities or decisions.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'PostgreSQL requis.');
        $this->addSql('ALTER TABLE client ADD contact_first_name VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE client ADD contact_last_name VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE client ADD contact_role VARCHAR(120) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'PostgreSQL requis.');
        $this->addSql('ALTER TABLE client DROP contact_first_name');
        $this->addSql('ALTER TABLE client DROP contact_last_name');
        $this->addSql('ALTER TABLE client DROP contact_role');
    }
}
