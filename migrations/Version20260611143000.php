<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le token d export iCal des logements et sécurise la granularité journalière du calendrier';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE properties ADD calendar_export_token VARCHAR(64) DEFAULT NULL');
        $this->addSql(<<<'SQL'
            UPDATE properties
            SET calendar_export_token = md5(id::text || clock_timestamp()::text) || md5(random()::text || id::text)
            WHERE calendar_export_token IS NULL
        SQL);
        $this->addSql('ALTER TABLE properties ALTER COLUMN calendar_export_token SET NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_PROPERTIES_CALENDAR_EXPORT_TOKEN ON properties (calendar_export_token)');

        $this->addSql('ALTER TABLE property_availability ADD blocked_reason TEXT DEFAULT NULL');
        $this->addSql(<<<'SQL'
            DELETE FROM property_availability pa
            USING (
                SELECT id
                FROM (
                    SELECT id, ROW_NUMBER() OVER (PARTITION BY property_id, available_date ORDER BY id) AS row_num
                    FROM property_availability
                ) ranked
                WHERE ranked.row_num > 1
            ) duplicates
            WHERE pa.id = duplicates.id
        SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_property_availability_property_day ON property_availability (property_id, available_date)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_property_availability_property_day');
        $this->addSql('ALTER TABLE property_availability DROP blocked_reason');

        $this->addSql('DROP INDEX UNIQ_PROPERTIES_CALENDAR_EXPORT_TOKEN');
        $this->addSql('ALTER TABLE properties DROP calendar_export_token');
    }
}
