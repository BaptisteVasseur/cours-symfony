<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la table unavailabilities (blocages hôte par période) et la colonne ical_token sur properties.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE unavailabilities (start_date DATE NOT NULL, end_date DATE NOT NULL, reason TEXT DEFAULT NULL, source VARCHAR(32) NOT NULL, external_uid VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, id UUID NOT NULL, property_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_E08E40E4549213EC ON unavailabilities (property_id)');
        $this->addSql('CREATE INDEX idx_unavailability_property_dates ON unavailabilities (property_id, start_date, end_date)');
        $this->addSql('ALTER TABLE unavailabilities ADD CONSTRAINT FK_E08E40E4549213EC FOREIGN KEY (property_id) REFERENCES properties (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('ALTER TABLE properties ADD ical_token VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_87C331C79F0C901D ON properties (ical_token)');

        $this->addSql('ALTER TABLE users ALTER roles DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ALTER roles SET DEFAULT \'[]\'');

        $this->addSql('DROP INDEX UNIQ_87C331C79F0C901D');
        $this->addSql('ALTER TABLE properties DROP ical_token');

        $this->addSql('ALTER TABLE unavailabilities DROP CONSTRAINT FK_E08E40E4549213EC');
        $this->addSql('DROP TABLE unavailabilities');
    }
}
