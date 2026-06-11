<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les periodes de disponibilite et le token export iCal';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE property_availability ADD date_start DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE property_availability ADD date_end DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE property_availability ADD reason TEXT DEFAULT NULL');
        $this->addSql("UPDATE property_availability SET date_start = available_date, date_end = available_date + INTERVAL '1 day' WHERE available_date IS NOT NULL AND date_start IS NULL");
        $this->addSql("UPDATE property_availability SET date_start = CURRENT_DATE, date_end = CURRENT_DATE + INTERVAL '1 day' WHERE date_start IS NULL OR date_end IS NULL");
        $this->addSql('ALTER TABLE property_availability ALTER date_start SET NOT NULL');
        $this->addSql('ALTER TABLE property_availability ALTER date_end SET NOT NULL');
        $this->addSql('ALTER TABLE property_availability ALTER available_date DROP NOT NULL');
        $this->addSql('CREATE INDEX IDX_PROPERTY_AVAILABILITY_PERIOD ON property_availability (property_id, date_start, date_end)');

        $this->addSql('ALTER TABLE properties ADD i_cal_export_token VARCHAR(64) DEFAULT NULL');
        $this->addSql("UPDATE properties SET i_cal_export_token = md5(id::text || clock_timestamp()::text || random()::text) WHERE i_cal_export_token IS NULL");
        $this->addSql('CREATE UNIQUE INDEX UNIQ_PROPERTIES_ICAL_EXPORT_TOKEN ON properties (i_cal_export_token)');

        $this->addSql('CREATE INDEX IDX_RESERVATIONS_PROPERTY_STATUS_DATES ON reservations (property_id, status, checkin_date, checkout_date)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_RESERVATIONS_PROPERTY_STATUS_DATES');
        $this->addSql('DROP INDEX UNIQ_PROPERTIES_ICAL_EXPORT_TOKEN');
        $this->addSql('ALTER TABLE properties DROP i_cal_export_token');

        $this->addSql('DROP INDEX IDX_PROPERTY_AVAILABILITY_PERIOD');
        $this->addSql('UPDATE property_availability SET available_date = date_start WHERE available_date IS NULL');
        $this->addSql('ALTER TABLE property_availability ALTER available_date SET NOT NULL');
        $this->addSql('ALTER TABLE property_availability DROP reason');
        $this->addSql('ALTER TABLE property_availability DROP date_end');
        $this->addSql('ALTER TABLE property_availability DROP date_start');
    }
}
