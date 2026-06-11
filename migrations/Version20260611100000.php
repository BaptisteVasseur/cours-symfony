<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add booking engine fields: expiresAt, cancelledBy, updatedAt on reservations; calendarToken on properties and users';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservations ADD expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE reservations ADD cancelled_by VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE reservations ADD updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN reservations.expires_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN reservations.updated_at IS \'(DC2Type:datetime_immutable)\'');

        $this->addSql('ALTER TABLE properties ADD calendar_token VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8A2CA0E8A240F98E ON properties (calendar_token)');

        $this->addSql('ALTER TABLE users ADD calendar_token VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9A240F98E ON users (calendar_token)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservations DROP expires_at');
        $this->addSql('ALTER TABLE reservations DROP cancelled_by');
        $this->addSql('ALTER TABLE reservations DROP updated_at');

        $this->addSql('DROP INDEX UNIQ_8A2CA0E8A240F98E');
        $this->addSql('ALTER TABLE properties DROP calendar_token');

        $this->addSql('DROP INDEX UNIQ_1483A5E9A240F98E');
        $this->addSql('ALTER TABLE users DROP calendar_token');
    }
}
