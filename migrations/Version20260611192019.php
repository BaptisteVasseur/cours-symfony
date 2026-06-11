<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260611192019 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE property_google_calendar_sync (google_calendar_id VARCHAR(255) DEFAULT NULL, access_token TEXT DEFAULT NULL, refresh_token TEXT DEFAULT NULL, token_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, sync_enabled BOOLEAN NOT NULL, last_sync_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, last_error TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, id UUID NOT NULL, property_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_515650E1549213EC ON property_google_calendar_sync (property_id)');
        $this->addSql('ALTER TABLE property_google_calendar_sync ADD CONSTRAINT FK_515650E1549213EC FOREIGN KEY (property_id) REFERENCES properties (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE property_google_calendar_sync DROP CONSTRAINT FK_515650E1549213EC');
        $this->addSql('DROP TABLE property_google_calendar_sync');
    }
}
