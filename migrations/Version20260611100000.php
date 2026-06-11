<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout du token iCal sur les logements';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE properties ADD calendar_token VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_87C331C7A4AE2B6D ON properties (calendar_token)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_87C331C7A4AE2B6D ON properties');
        $this->addSql('ALTER TABLE properties DROP calendar_token');
    }
}
