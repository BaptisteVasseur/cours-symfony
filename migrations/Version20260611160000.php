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

        // changed_by null = transition automatique du système (expiration G.1)
        $this->addSql('ALTER TABLE reservation_status_history ALTER changed_by_id DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notifications DROP link_url');
        $this->addSql('ALTER TABLE reservation_status_history ALTER changed_by_id SET NOT NULL');
    }
}
