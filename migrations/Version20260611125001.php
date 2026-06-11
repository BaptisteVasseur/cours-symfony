<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260611125001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Empêche le chevauchement de deux réservations confirmées sur un même logement (contrainte EXCLUDE gist).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE EXTENSION IF NOT EXISTS btree_gist');
        $this->addSql(<<<'SQL'
            ALTER TABLE reservations ADD CONSTRAINT no_overlap_confirmed
            EXCLUDE USING gist (
                property_id WITH =,
                daterange(checkin_date, checkout_date, '[)') WITH &&
            ) WHERE (status = 'confirmed')
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservations DROP CONSTRAINT no_overlap_confirmed');
    }
}
