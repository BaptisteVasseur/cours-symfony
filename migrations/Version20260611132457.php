<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260611132457 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'PropertyAvailability : lien vers le flux iCal importé (ical_sync_id) + external_uid';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE property_availability ADD external_uid VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE property_availability ADD ical_sync_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE property_availability ADD CONSTRAINT FK_F0B252E668508707 FOREIGN KEY (ical_sync_id) REFERENCES property_ical_sync (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_F0B252E668508707 ON property_availability (ical_sync_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE property_availability DROP CONSTRAINT FK_F0B252E668508707');
        $this->addSql('DROP INDEX IDX_F0B252E668508707');
        $this->addSql('ALTER TABLE property_availability DROP external_uid');
        $this->addSql('ALTER TABLE property_availability DROP ical_sync_id');
    }
}
