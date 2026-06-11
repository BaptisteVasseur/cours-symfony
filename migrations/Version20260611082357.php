<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260611082357 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE property_availability DROP CONSTRAINT fk_f0b252e6549213ec');
        $this->addSql('DROP TABLE property_availability');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE property_availability (available_date DATE NOT NULL, is_available BOOLEAN NOT NULL, price_override NUMERIC(10, 2) DEFAULT NULL, minimum_stay INT DEFAULT NULL, id UUID NOT NULL, property_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_f0b252e6549213ec ON property_availability (property_id)');
        $this->addSql('ALTER TABLE property_availability ADD CONSTRAINT fk_f0b252e6549213ec FOREIGN KEY (property_id) REFERENCES properties (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
