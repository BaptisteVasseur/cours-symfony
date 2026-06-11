<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260611093418 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le motif d\'indisponibilité (block_reason) aux disponibilités d\'un logement.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE property_availability ADD block_reason VARCHAR(50) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE property_availability DROP block_reason');
    }
}
