<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260611103116 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE property_unavailabilities (start_date DATE NOT NULL, end_date DATE NOT NULL, reason VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, id UUID NOT NULL, property_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_95E35878549213EC ON property_unavailabilities (property_id)');
        $this->addSql('CREATE INDEX idx_unavailability_overlap ON property_unavailabilities (property_id, start_date, end_date)');
        $this->addSql('ALTER TABLE property_unavailabilities ADD CONSTRAINT FK_95E35878549213EC FOREIGN KEY (property_id) REFERENCES properties (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE property_unavailabilities DROP CONSTRAINT FK_95E35878549213EC');
        $this->addSql('DROP TABLE property_unavailabilities');
    }
}
