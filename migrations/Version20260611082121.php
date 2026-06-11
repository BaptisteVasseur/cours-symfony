<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260611082121 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout availability_schedules, availability_exceptions, ical_token, enrichissement property_ical_sync';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE availability_exceptions (date DATE NOT NULL, reason VARCHAR(255) DEFAULT NULL, source VARCHAR(50) NOT NULL, id UUID NOT NULL, property_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_5A0F5734549213EC ON availability_exceptions (property_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_exception_property_date ON availability_exceptions (property_id, date)');
        $this->addSql('CREATE TABLE availability_schedules (start_date DATE NOT NULL, end_date DATE NOT NULL, days_of_week JSON DEFAULT NULL, check_in_time TIME(0) WITHOUT TIME ZONE NOT NULL, check_out_time TIME(0) WITHOUT TIME ZONE NOT NULL, minimum_stay INT NOT NULL, maximum_stay INT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, id UUID NOT NULL, property_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_4B100D48549213EC ON availability_schedules (property_id)');
        $this->addSql('CREATE INDEX idx_schedule_property_dates ON availability_schedules (property_id, start_date, end_date)');
        $this->addSql('ALTER TABLE availability_exceptions ADD CONSTRAINT FK_5A0F5734549213EC FOREIGN KEY (property_id) REFERENCES properties (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE availability_schedules ADD CONSTRAINT FK_4B100D48549213EC FOREIGN KEY (property_id) REFERENCES properties (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE properties ADD ical_token UUID DEFAULT NULL');
        $this->addSql('UPDATE properties SET ical_token = gen_random_uuid() WHERE ical_token IS NULL');
        $this->addSql('ALTER TABLE properties ALTER COLUMN ical_token SET NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_87C331C79F0C901D ON properties (ical_token)');
        $this->addSql('ALTER TABLE property_ical_sync ADD sync_status VARCHAR(50) NOT NULL DEFAULT \'pending\'');
        $this->addSql('ALTER TABLE property_ical_sync ADD error_message TEXT DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_ical_sync_property_url ON property_ical_sync (property_id, i_cal_url)');
        $this->addSql('ALTER TABLE users ALTER roles DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE availability_exceptions DROP CONSTRAINT FK_5A0F5734549213EC');
        $this->addSql('ALTER TABLE availability_schedules DROP CONSTRAINT FK_4B100D48549213EC');
        $this->addSql('DROP TABLE availability_exceptions');
        $this->addSql('DROP TABLE availability_schedules');
        $this->addSql('DROP INDEX UNIQ_87C331C79F0C901D');
        $this->addSql('ALTER TABLE properties DROP ical_token');
        $this->addSql('DROP INDEX uniq_ical_sync_property_url');
        $this->addSql('ALTER TABLE property_ical_sync DROP sync_status');
        $this->addSql('ALTER TABLE property_ical_sync DROP error_message');
        $this->addSql('ALTER TABLE users ALTER roles SET DEFAULT \'[]\'');
    }
}
