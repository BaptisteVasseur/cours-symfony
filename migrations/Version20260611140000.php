<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'iCal : token d\'export sur properties, traçabilité d\'import sur property_blocked_period';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE properties ADD ical_export_token VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_PROPERTIES_ICAL_TOKEN ON properties (ical_export_token)');

        $this->addSql('ALTER TABLE property_blocked_period ADD sync_source_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE property_blocked_period ADD external_uid VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE property_blocked_period ADD CONSTRAINT FK_BLOCKED_PERIOD_SYNC FOREIGN KEY (sync_source_id) REFERENCES property_ical_sync (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_BLOCKED_PERIOD_SYNC ON property_blocked_period (sync_source_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE property_blocked_period DROP CONSTRAINT FK_BLOCKED_PERIOD_SYNC');
        $this->addSql('DROP INDEX IDX_BLOCKED_PERIOD_SYNC');
        $this->addSql('ALTER TABLE property_blocked_period DROP external_uid');
        $this->addSql('ALTER TABLE property_blocked_period DROP sync_source_id');

        $this->addSql('DROP INDEX UNIQ_PROPERTIES_ICAL_TOKEN');
        $this->addSql('ALTER TABLE properties DROP ical_export_token');
    }
}
