<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260611162557 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE property_blocked_periods (start_date DATE NOT NULL, end_date DATE NOT NULL, reason VARCHAR(50) NOT NULL, note TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, id UUID NOT NULL, property_id UUID NOT NULL, created_by_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_D97EF096549213EC ON property_blocked_periods (property_id)');
        $this->addSql('CREATE INDEX IDX_D97EF096B03A8386 ON property_blocked_periods (created_by_id)');
        $this->addSql('ALTER TABLE property_blocked_periods ADD CONSTRAINT FK_D97EF096549213EC FOREIGN KEY (property_id) REFERENCES properties (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE property_blocked_periods ADD CONSTRAINT FK_D97EF096B03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE users ALTER roles DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE property_blocked_periods DROP CONSTRAINT FK_D97EF096549213EC');
        $this->addSql('ALTER TABLE property_blocked_periods DROP CONSTRAINT FK_D97EF096B03A8386');
        $this->addSql('DROP TABLE property_blocked_periods');
        $this->addSql('ALTER TABLE users ALTER roles SET DEFAULT \'["ROLE_USER"]\'');
    }
}
