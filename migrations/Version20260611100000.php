<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée la table property_blocked_period (indisponibilités hôte à la minute près)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE property_blocked_period (
                id UUID NOT NULL,
                property_id UUID NOT NULL,
                start_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                end_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                reason VARCHAR(255) DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
            SQL);
        $this->addSql('CREATE INDEX IDX_BLOCKED_PERIOD_PROPERTY ON property_blocked_period (property_id)');
        $this->addSql('CREATE INDEX IDX_BLOCKED_PERIOD_RANGE ON property_blocked_period (property_id, start_at, end_at)');
        $this->addSql('ALTER TABLE property_blocked_period ADD CONSTRAINT FK_BLOCKED_PERIOD_PROPERTY FOREIGN KEY (property_id) REFERENCES properties (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE property_blocked_period DROP CONSTRAINT FK_BLOCKED_PERIOD_PROPERTY');
        $this->addSql('DROP TABLE property_blocked_period');
    }
}
