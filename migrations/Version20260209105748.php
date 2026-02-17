<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260209105748 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE property ALTER image DROP DEFAULT');
        $this->addSql('ALTER TABLE "user" ADD state VARCHAR(255) NOT NULL DEFAULT \'waiting\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE property ALTER image SET DEFAULT \'\'');
        $this->addSql('ALTER TABLE "user" DROP state');
    }
}
