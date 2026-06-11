<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adapte property_availability : remplace available_date/is_available par start_date/end_date/reason (approche périodes)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE property_availability ADD start_date DATE NOT NULL DEFAULT CURRENT_DATE');
        $this->addSql('ALTER TABLE property_availability ADD end_date DATE NOT NULL DEFAULT CURRENT_DATE');
        $this->addSql('ALTER TABLE property_availability ADD reason VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE property_availability DROP COLUMN IF EXISTS available_date');
        $this->addSql('ALTER TABLE property_availability DROP COLUMN IF EXISTS is_available');
        $this->addSql('ALTER TABLE property_availability ALTER COLUMN start_date DROP DEFAULT');
        $this->addSql('ALTER TABLE property_availability ALTER COLUMN end_date DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE property_availability ADD available_date DATE NOT NULL DEFAULT CURRENT_DATE');
        $this->addSql('ALTER TABLE property_availability ADD is_available BOOLEAN NOT NULL DEFAULT TRUE');
        $this->addSql('ALTER TABLE property_availability DROP COLUMN IF EXISTS start_date');
        $this->addSql('ALTER TABLE property_availability DROP COLUMN IF EXISTS end_date');
        $this->addSql('ALTER TABLE property_availability DROP COLUMN IF EXISTS reason');
        $this->addSql('ALTER TABLE property_availability ALTER COLUMN available_date DROP DEFAULT');
    }
}
