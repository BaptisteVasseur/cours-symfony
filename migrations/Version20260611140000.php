<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ical_sync_source to property_availability to track origin of iCal-blocked dates';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE property_availability ADD ical_sync_source VARCHAR(100) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE property_availability DROP COLUMN ical_sync_source');
    }
}
