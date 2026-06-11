<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611122000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Prepare reservation calendar model for iCal export and pending expiration';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE properties ADD calendar_token VARCHAR(64) DEFAULT NULL');
        $this->addSql("UPDATE properties SET calendar_token = md5(random()::text || clock_timestamp()::text || id::text) || md5(id::text || random()::text)");
        $this->addSql('ALTER TABLE properties ALTER calendar_token SET NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_87C331C7D775A03D ON properties (calendar_token)');

        $this->addSql("ALTER TABLE property_availability ADD source VARCHAR(50) DEFAULT 'manual' NOT NULL");
        $this->addSql('ALTER TABLE property_availability ADD reason TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE property_availability ADD external_uid VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_PROPERTY_AVAILABILITY_DAY ON property_availability (property_id, available_date)');
        $this->addSql('CREATE INDEX IDX_PROPERTY_AVAILABILITY_RANGE ON property_availability (property_id, available_date, is_available)');

        $this->addSql('ALTER TABLE reservations ADD expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql("UPDATE reservations SET expires_at = created_at + INTERVAL '7 days' WHERE status = 'pending'");
        $this->addSql('CREATE INDEX IDX_RESERVATION_PENDING_EXPIRATION ON reservations (status, expires_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_RESERVATION_PENDING_EXPIRATION');
        $this->addSql('ALTER TABLE reservations DROP expires_at');

        $this->addSql('DROP INDEX IDX_PROPERTY_AVAILABILITY_RANGE');
        $this->addSql('DROP INDEX UNIQ_PROPERTY_AVAILABILITY_DAY');
        $this->addSql('ALTER TABLE property_availability DROP external_uid');
        $this->addSql('ALTER TABLE property_availability DROP reason');
        $this->addSql('ALTER TABLE property_availability DROP source');

        $this->addSql('DROP INDEX UNIQ_87C331C7D775A03D');
        $this->addSql('ALTER TABLE properties DROP calendar_token');
    }
}
