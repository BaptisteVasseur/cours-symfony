<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611113000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add host iCal token on users';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD host_ical_token VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9A4A79AF9 ON users (host_ical_token)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_1483A5E9A4A79AF9');
        $this->addSql('ALTER TABLE users DROP host_ical_token');
    }
}

