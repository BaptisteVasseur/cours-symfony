<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute reservation instantanee, token iCal logement et motifs de refus/annulation.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE logement ADD instant_booking BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE logement ADD ical_token VARCHAR(64) DEFAULT NULL');
        $this->addSql("UPDATE logement SET ical_token = md5(random()::text || '-' || id::text || '-' || clock_timestamp()::text) || md5(id::text || '-' || random()::text) WHERE ical_token IS NULL");
        $this->addSql('ALTER TABLE logement ALTER ical_token SET NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_F0FD4457E84DE2D4 ON logement (ical_token)');
        $this->addSql('ALTER TABLE reservation ADD motif_refus TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE reservation ADD motif_annulation TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_F0FD4457E84DE2D4');
        $this->addSql('ALTER TABLE logement DROP instant_booking');
        $this->addSql('ALTER TABLE logement DROP ical_token');
        $this->addSql('ALTER TABLE reservation DROP motif_refus');
        $this->addSql('ALTER TABLE reservation DROP motif_annulation');
    }
}
