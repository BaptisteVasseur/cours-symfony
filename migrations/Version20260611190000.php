<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le bus d evenements realtime pour la diffusion WebSocket.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE realtime_events (id SERIAL NOT NULL, type VARCHAR(100) NOT NULL, recipient_user_id VARCHAR(36) DEFAULT NULL, topic VARCHAR(255) DEFAULT NULL, payload JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_realtime_recipient ON realtime_events (recipient_user_id)');
        $this->addSql('CREATE INDEX idx_realtime_topic ON realtime_events (topic)');
        $this->addSql('CREATE INDEX idx_realtime_created_at ON realtime_events (created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE realtime_events');
    }
}
