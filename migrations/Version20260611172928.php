<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611172928 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Partie A : table property_unavailability (périodes bloquées par l\'hôte avec motif) + index de disponibilité sur reservations';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE property_unavailability (start_date DATE NOT NULL, end_date DATE NOT NULL, reason VARCHAR(255) NOT NULL, note TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, id UUID NOT NULL, property_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_D9C4CDD5549213EC ON property_unavailability (property_id)');
        $this->addSql('CREATE INDEX idx_unavailability_property_period ON property_unavailability (property_id, start_date, end_date)');
        $this->addSql('ALTER TABLE property_unavailability ADD CONSTRAINT FK_D9C4CDD5549213EC FOREIGN KEY (property_id) REFERENCES properties (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX idx_reservation_availability ON reservations (property_id, status, checkin_date, checkout_date)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE property_unavailability DROP CONSTRAINT FK_D9C4CDD5549213EC');
        $this->addSql('DROP TABLE property_unavailability');
        $this->addSql('DROP INDEX idx_reservation_availability');
    }
}
