<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611125500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute l origine des disponibilites et le lien vers les sources iCal importees';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->getTable('property_availability')->hasColumn('source')) {
            $this->addSql("ALTER TABLE property_availability ADD source VARCHAR(20) NOT NULL DEFAULT 'manual'");
        }

        if (!$schema->getTable('property_availability')->hasColumn('property_ical_sync_id')) {
            $this->addSql('ALTER TABLE property_availability ADD property_ical_sync_id UUID DEFAULT NULL');
            $this->addSql('CREATE INDEX IDX_PROPERTY_AVAILABILITY_ICAL_SYNC ON property_availability (property_ical_sync_id)');
            $this->addSql('ALTER TABLE property_availability ADD CONSTRAINT FK_PROPERTY_AVAILABILITY_ICAL_SYNC FOREIGN KEY (property_ical_sync_id) REFERENCES property_ical_sync (id) ON DELETE SET NULL NOT DEFERRABLE');
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->getTable('property_availability')->hasColumn('property_ical_sync_id')) {
            $this->addSql('ALTER TABLE property_availability DROP CONSTRAINT IF EXISTS FK_PROPERTY_AVAILABILITY_ICAL_SYNC');
            $this->addSql('DROP INDEX IF EXISTS IDX_PROPERTY_AVAILABILITY_ICAL_SYNC');
            $this->addSql('ALTER TABLE property_availability DROP COLUMN property_ical_sync_id');
        }

        if ($schema->getTable('property_availability')->hasColumn('source')) {
            $this->addSql('ALTER TABLE property_availability DROP COLUMN source');
        }
    }
}
