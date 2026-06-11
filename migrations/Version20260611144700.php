<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Moteur de réservation iCal :
 *  - properties.ical_export_token : jeton d'export iCal, unique et révocable par l'hôte ;
 *  - property_availability.source : origine du blocage ('host' ou 'ical:{provider}').
 */
final class Version20260611144700 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le jeton d\'export iCal (properties) et la source de blocage (property_availability).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE properties ADD ical_export_token VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_87C331C7AB3E94C2 ON properties (ical_export_token)');
        $this->addSql('ALTER TABLE property_availability ADD source VARCHAR(100) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_87C331C7AB3E94C2');
        $this->addSql('ALTER TABLE properties DROP ical_export_token');
        $this->addSql('ALTER TABLE property_availability DROP source');
    }
}
