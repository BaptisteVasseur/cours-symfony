<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611114000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le token d export iCal securise sur les logements';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->getTable('properties')->hasColumn('ical_export_token')) {
            $this->addSql('ALTER TABLE properties ADD ical_export_token VARCHAR(64) DEFAULT NULL');
            $this->addSql("UPDATE properties SET ical_export_token = md5(id::text || random()::text || clock_timestamp()::text) || md5(random()::text || clock_timestamp()::text || id::text) WHERE ical_export_token IS NULL");
            $this->addSql('ALTER TABLE properties ALTER COLUMN ical_export_token SET NOT NULL');
            $this->addSql('CREATE UNIQUE INDEX UNIQ_PROPERTIES_ICAL_EXPORT_TOKEN ON properties (ical_export_token)');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS UNIQ_PROPERTIES_ICAL_EXPORT_TOKEN');

        if ($schema->getTable('properties')->hasColumn('ical_export_token')) {
            $this->addSql('ALTER TABLE properties DROP COLUMN ical_export_token');
        }
    }
}
