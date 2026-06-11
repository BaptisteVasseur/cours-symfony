<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le suivi des blocages importes depuis iCal';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE property_availability ADD source VARCHAR(20) NOT NULL DEFAULT 'manual'");
        $this->addSql('ALTER TABLE property_availability ADD external_uid VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE property_availability ADD i_cal_sync_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE property_availability ADD last_seen_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_PROPERTY_AVAILABILITY_ICAL_SYNC ON property_availability (i_cal_sync_id, external_uid)');
        $this->addSql('CREATE INDEX IDX_PROPERTY_AVAILABILITY_SOURCE ON property_availability (source)');
        $this->addSql('ALTER TABLE property_availability ADD CONSTRAINT FK_PROPERTY_AVAILABILITY_ICAL_SYNC FOREIGN KEY (i_cal_sync_id) REFERENCES property_ical_sync (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE property_availability DROP CONSTRAINT FK_PROPERTY_AVAILABILITY_ICAL_SYNC');
        $this->addSql('DROP INDEX IDX_PROPERTY_AVAILABILITY_SOURCE');
        $this->addSql('DROP INDEX IDX_PROPERTY_AVAILABILITY_ICAL_SYNC');
        $this->addSql('ALTER TABLE property_availability DROP last_seen_at');
        $this->addSql('ALTER TABLE property_availability DROP i_cal_sync_id');
        $this->addSql('ALTER TABLE property_availability DROP external_uid');
        $this->addSql('ALTER TABLE property_availability DROP source');
    }
}
