<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261010150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Alignement des noms d index Doctrine, sans suppression ni reconstruction';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'PostgreSQL requis.');

        $this->addSql('ALTER INDEX idx_evidence_decision RENAME TO IDX_2200889BDEE7539');
        $this->addSql('ALTER INDEX uniq_app_user_email RENAME TO UNIQ_88BDF3E9E7927C74');
        $this->addSql('ALTER INDEX uniq_consent_request_token RENAME TO UNIQ_9007BFA3B3BC57DA');
        $this->addSql('ALTER INDEX idx_consent_request_client RENAME TO IDX_9007BFA319EB6921');
        $this->addSql('ALTER INDEX idx_audit_client RENAME TO IDX_3CE56E5019EB6921');
        $this->addSql('ALTER INDEX idx_audit_request RENAME TO IDX_3CE56E50427EB8A5');
        $this->addSql('ALTER INDEX uniq_consent_decision_request RENAME TO UNIQ_AB6852FF427EB8A5');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'PostgreSQL requis.');

        $this->addSql('ALTER INDEX idx_2200889bdee7539 RENAME TO IDX_EVIDENCE_DECISION');
        $this->addSql('ALTER INDEX uniq_88bdf3e9e7927c74 RENAME TO UNIQ_APP_USER_EMAIL');
        $this->addSql('ALTER INDEX uniq_9007bfa3b3bc57da RENAME TO UNIQ_CONSENT_REQUEST_TOKEN');
        $this->addSql('ALTER INDEX idx_9007bfa319eb6921 RENAME TO IDX_CONSENT_REQUEST_CLIENT');
        $this->addSql('ALTER INDEX idx_3ce56e5019eb6921 RENAME TO IDX_AUDIT_CLIENT');
        $this->addSql('ALTER INDEX idx_3ce56e50427eb8a5 RENAME TO IDX_AUDIT_REQUEST');
        $this->addSql('ALTER INDEX uniq_ab6852ff427eb8a5 RENAME TO UNIQ_CONSENT_DECISION_REQUEST');
    }
}
