<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ical_token to properties table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE properties ADD ical_token VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_87C331C7EF11B6C5 ON properties (ical_token)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_87C331C7EF11B6C5');
        $this->addSql('ALTER TABLE properties DROP ical_token');
    }
}
