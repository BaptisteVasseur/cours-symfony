<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611103000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Empêche deux réservations confirmées de se chevaucher pour un même logement';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE EXTENSION IF NOT EXISTS btree_gist');
        $this->addSql(<<<'SQL'
            ALTER TABLE reservations
            ADD CONSTRAINT no_overlapping_confirmed_reservations
            EXCLUDE USING gist (
                property_id WITH =,
                daterange(checkin_date, checkout_date, '[)') WITH &&
            )
            WHERE (status = 'confirmed')
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservations DROP CONSTRAINT IF EXISTS no_overlapping_confirmed_reservations');
    }
}
