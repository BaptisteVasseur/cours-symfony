<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la colonne ical_export_token sur properties';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE properties ADD ical_export_token VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_87C331C7ICAL_TOKEN ON properties (ical_export_token)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_87C331C7ICAL_TOKEN');
        $this->addSql('ALTER TABLE properties DROP ical_export_token');
    }
}
