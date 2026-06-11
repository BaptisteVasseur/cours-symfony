<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;


final class Version20260608082333 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add date of birth for send newsletter';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD dob TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT NOW()');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP dob');
    }
}
