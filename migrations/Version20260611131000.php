<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611131000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Aligne le default Doctrine de property_availability.source';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE property_availability ALTER source DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE property_availability ALTER source SET DEFAULT 'manual'");
    }
}
