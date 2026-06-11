<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260611131458 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Property : token iCal d\'export (unique, révocable)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE properties ADD ical_token VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_87C331C79F0C901D ON properties (ical_token)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_87C331C79F0C901D');
        $this->addSql('ALTER TABLE properties DROP ical_token');
    }
}
