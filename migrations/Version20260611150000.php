<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le suivi d envoi du rappel email J-1 des reservations';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservations ADD reminder_sent_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservations DROP reminder_sent_at');
    }
}
