<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611101755 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les indisponibilites par periode et la duree minimale de sejour.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE availability_block (start_date DATE NOT NULL, end_date DATE NOT NULL, reason VARCHAR(255) NOT NULL, notes TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, id UUID NOT NULL, property_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_47CAC266549213EC ON availability_block (property_id)');
        $this->addSql('CREATE INDEX idx_avail_block ON availability_block (property_id, start_date, end_date)');
        $this->addSql('ALTER TABLE availability_block ADD CONSTRAINT FK_47CAC266549213EC FOREIGN KEY (property_id) REFERENCES properties (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE properties ADD min_stay_nights INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE availability_block DROP CONSTRAINT FK_47CAC266549213EC');
        $this->addSql('DROP TABLE availability_block');
        $this->addSql('ALTER TABLE properties DROP min_stay_nights');
    }
}
