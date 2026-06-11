<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute allow_same_day_booking (arrivée le jour d'un départ) et minimum_stay
 * (durée de séjour minimum) sur la table properties.
 */
final class Version20260611120033 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Property : allow_same_day_booking + minimum_stay';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE properties ADD allow_same_day_booking BOOLEAN DEFAULT FALSE NOT NULL');
        $this->addSql('ALTER TABLE properties ALTER allow_same_day_booking DROP DEFAULT');
        $this->addSql('ALTER TABLE properties ADD minimum_stay INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE properties DROP allow_same_day_booking');
        $this->addSql('ALTER TABLE properties DROP minimum_stay');
    }
}
