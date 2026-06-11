<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add property_blocks table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE property_blocks (id UUID NOT NULL, property_id UUID NOT NULL, date_start DATE NOT NULL, date_end DATE NOT NULL, reason VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_property_blocks_property ON property_blocks (property_id)');
        $this->addSql('COMMENT ON COLUMN property_blocks.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN property_blocks.property_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN property_blocks.date_start IS \'(DC2Type:date_immutable)\'');
        $this->addSql('COMMENT ON COLUMN property_blocks.date_end IS \'(DC2Type:date_immutable)\'');
        $this->addSql('ALTER TABLE property_blocks ADD CONSTRAINT FK_property_blocks_property FOREIGN KEY (property_id) REFERENCES properties (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE property_blocks DROP CONSTRAINT FK_property_blocks_property');
        $this->addSql('DROP TABLE property_blocks');
    }
}
