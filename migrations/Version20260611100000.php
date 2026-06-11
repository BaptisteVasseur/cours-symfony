<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Change checkin_date and checkout_date from DATE to TIMESTAMP in reservations';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservations ALTER COLUMN checkin_date TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE reservations ALTER COLUMN checkout_date TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservations ALTER COLUMN checkin_date TYPE DATE');
        $this->addSql('ALTER TABLE reservations ALTER COLUMN checkout_date TYPE DATE');
    }
}
