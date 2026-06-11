<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611191000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Resynchronise la sequence identity de realtime_events.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("SELECT setval(pg_get_serial_sequence('realtime_events', 'id'), COALESCE((SELECT MAX(id) FROM realtime_events), 1), (SELECT COUNT(*) > 0 FROM realtime_events))");
    }

    public function down(Schema $schema): void
    {
    }
}
