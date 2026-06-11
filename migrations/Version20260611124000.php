<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611124000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Supprime l\'index orphelin idx_reservation_pending_expiration absent du mapping Doctrine';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_reservation_pending_expiration');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_reservation_pending_expiration ON reservations (status, expires_at) WHERE ((status)::text = \'pending\'::text)');
    }
}
