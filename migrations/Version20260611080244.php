<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611080244 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Change property_availability';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE property_availability ADD reservation_id UUID NOT NULL');
        $this->addSql('ALTER TABLE property_availability DROP is_available');
        $this->addSql('ALTER TABLE property_availability DROP price_override');
        $this->addSql('ALTER TABLE property_availability DROP minimum_stay');
        $this->addSql('ALTER TABLE property_availability RENAME COLUMN available_date TO occupied_date');
        $this->addSql('ALTER TABLE property_availability ADD CONSTRAINT FK_F0B252E6B83297E7 FOREIGN KEY (reservation_id) REFERENCES reservations (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_F0B252E6B83297E7 ON property_availability (reservation_id)');
        $this->addSql('ALTER TABLE users ALTER roles DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE property_availability DROP CONSTRAINT FK_F0B252E6B83297E7');
        $this->addSql('DROP INDEX IDX_F0B252E6B83297E7');
        $this->addSql('ALTER TABLE property_availability ADD is_available BOOLEAN NOT NULL');
        $this->addSql('ALTER TABLE property_availability ADD price_override NUMERIC(10, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE property_availability ADD minimum_stay INT DEFAULT NULL');
        $this->addSql('ALTER TABLE property_availability DROP reservation_id');
        $this->addSql('ALTER TABLE property_availability RENAME COLUMN occupied_date TO available_date');
        $this->addSql('ALTER TABLE users ALTER roles SET DEFAULT \'[]\'');
    }
}
