<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add calendar_token to properties, ical_uid to property_blocks';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE properties ADD calendar_token VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_properties_calendar_token ON properties (calendar_token)');
        $this->addSql('ALTER TABLE property_blocks ADD ical_uid VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_properties_calendar_token');
        $this->addSql('ALTER TABLE properties DROP calendar_token');
        $this->addSql('ALTER TABLE property_blocks DROP ical_uid');
    }
}
