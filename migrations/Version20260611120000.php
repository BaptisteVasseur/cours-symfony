<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le token prive pour exporter le calendrier iCal des logements';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE properties ADD i_cal_export_token VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE properties ADD CONSTRAINT uniq_properties_ical_export_token UNIQUE (i_cal_export_token)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE properties DROP CONSTRAINT uniq_properties_ical_export_token');
        $this->addSql('ALTER TABLE properties DROP i_cal_export_token');
    }
}
