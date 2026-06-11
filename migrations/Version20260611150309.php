<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Rend reservation_status_history.changed_by_id nullable : une transition de
 * statut peut être initiée par le système (ex. expiration automatique d'une
 * demande), sans utilisateur auteur.
 */
final class Version20260611150309 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'reservation_status_history.changed_by_id devient nullable (transitions système).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservation_status_history ALTER changed_by_id DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservation_status_history ALTER changed_by_id SET NOT NULL');
    }
}
