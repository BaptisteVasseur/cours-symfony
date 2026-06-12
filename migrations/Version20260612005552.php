<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260612005552 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Token d\'export iCal unique et révocable par logement';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE properties ADD ical_export_token VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_87C331C7AB3E94C2 ON properties (ical_export_token)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_87C331C7AB3E94C2');
        $this->addSql('ALTER TABLE properties DROP ical_export_token');
    }
}
