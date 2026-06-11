<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute un lien profond (link_url) aux notifications in-app';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notifications ADD link_url VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notifications DROP link_url');
    }
}
