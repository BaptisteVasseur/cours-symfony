<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611085945 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout du blocage d\'un logement via réservation';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservations ADD type VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE reservations ADD block_reason TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservations DROP type');
        $this->addSql('ALTER TABLE reservations DROP block_reason');
    }
}
