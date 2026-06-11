<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611152000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Autorise un historique de statut sans utilisateur pour les expirations automatiques.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservation_status_history ALTER COLUMN changed_by_id DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM reservation_status_history WHERE changed_by_id IS NULL');
        $this->addSql('ALTER TABLE reservation_status_history ALTER COLUMN changed_by_id SET NOT NULL');
    }
}
