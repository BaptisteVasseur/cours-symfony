<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make changed_by_id nullable in reservation_status_history (system actions have no actor)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservation_status_history ALTER COLUMN changed_by_id DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('UPDATE reservation_status_history SET changed_by_id = (SELECT id FROM users ORDER BY created_at LIMIT 1) WHERE changed_by_id IS NULL');
        $this->addSql('ALTER TABLE reservation_status_history ALTER COLUMN changed_by_id SET NOT NULL');
    }
}
