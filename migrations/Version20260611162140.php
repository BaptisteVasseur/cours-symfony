<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260611162140 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE blockouts (start_date DATE NOT NULL, end_date DATE NOT NULL, reason VARCHAR(50) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, id UUID NOT NULL, property_id UUID NOT NULL, created_by_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_BD84DE46549213EC ON blockouts (property_id)');
        $this->addSql('CREATE INDEX IDX_BD84DE46B03A8386 ON blockouts (created_by_id)');
        $this->addSql('CREATE INDEX idx_blockout_property_dates ON blockouts (property_id, start_date, end_date)');
        $this->addSql('ALTER TABLE blockouts ADD CONSTRAINT FK_BD84DE46549213EC FOREIGN KEY (property_id) REFERENCES properties (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE blockouts ADD CONSTRAINT FK_BD84DE46B03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE properties ADD min_nights INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE blockouts DROP CONSTRAINT FK_BD84DE46549213EC');
        $this->addSql('ALTER TABLE blockouts DROP CONSTRAINT FK_BD84DE46B03A8386');
        $this->addSql('DROP TABLE blockouts');
        $this->addSql('ALTER TABLE properties DROP min_nights');
    }
}
