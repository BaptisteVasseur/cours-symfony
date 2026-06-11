<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Synchronize reservation calendar schema with Doctrine mapping';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER INDEX uniq_87c331c7d775a03d RENAME TO "UNIQ_87C331C73363A255"');
        $this->addSql('ALTER TABLE property_availability ALTER source DROP DEFAULT');
        $this->addSql('ALTER TABLE users ALTER roles DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER INDEX "UNIQ_87C331C73363A255" RENAME TO uniq_87c331c7d775a03d');
        $this->addSql("ALTER TABLE property_availability ALTER source SET DEFAULT 'manual'");
        $this->addSql("ALTER TABLE users ALTER roles SET DEFAULT '[]'");
    }
}
