<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Aligne le default Doctrine de users.roles';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ALTER roles DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE users ALTER roles SET DEFAULT '[]'");
    }
}
