<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260611075949 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE property_pricing (date DATE NOT NULL, price_override NUMERIC(10, 2) DEFAULT NULL, minimum_stay INT DEFAULT NULL, id UUID NOT NULL, property_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_BC3CE64B549213EC ON property_pricing (property_id)');
        $this->addSql('ALTER TABLE property_pricing ADD CONSTRAINT FK_BC3CE64B549213EC FOREIGN KEY (property_id) REFERENCES properties (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE property_availability DROP is_available');
        $this->addSql('ALTER TABLE property_availability DROP price_override');
        $this->addSql('ALTER TABLE property_availability DROP minimum_stay');
        $this->addSql('ALTER TABLE property_availability RENAME COLUMN available_date TO blocked_date');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE property_pricing DROP CONSTRAINT FK_BC3CE64B549213EC');
        $this->addSql('DROP TABLE property_pricing');
        $this->addSql('ALTER TABLE property_availability ADD is_available BOOLEAN NOT NULL');
        $this->addSql('ALTER TABLE property_availability ADD price_override NUMERIC(10, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE property_availability ADD minimum_stay INT DEFAULT NULL');
        $this->addSql('ALTER TABLE property_availability RENAME COLUMN blocked_date TO available_date');
    }
}
