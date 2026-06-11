<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * PropertyAvailability : passage du modèle 1 ligne/jour (available_date) à un modèle
 * d'intervalle (start_date/end_date) + motif de blocage (block_note).
 */
final class Version20260611113853 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'PropertyAvailability en intervalles start_date/end_date + block_note';
    }

    public function up(Schema $schema): void
    {
        // Les disponibilités sont régénérées par les fixtures ; on vide la table
        // pour pouvoir ajouter end_date en NOT NULL sans valeur par défaut.
        $this->addSql('TRUNCATE TABLE property_availability');
        $this->addSql('ALTER TABLE property_availability RENAME COLUMN available_date TO start_date');
        $this->addSql('ALTER TABLE property_availability ADD end_date DATE NOT NULL');
        $this->addSql('ALTER TABLE property_availability ADD block_note TEXT DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_availability_property_dates ON property_availability (property_id, start_date, end_date)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_availability_property_dates');
        $this->addSql('ALTER TABLE property_availability DROP end_date');
        $this->addSql('ALTER TABLE property_availability DROP block_note');
        $this->addSql('ALTER TABLE property_availability RENAME COLUMN start_date TO available_date');
    }
}
