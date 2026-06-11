<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260611191142 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Convert reservation checkin/checkout dates from DATE to TIMESTAMP to support time-aware conflict detection';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservations ALTER checkin_date TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE reservations ALTER checkout_date TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        // Set default times on existing rows: 15:00 checkin, 11:00 checkout
        $this->addSql("UPDATE reservations SET checkin_date  = checkin_date  + INTERVAL '15 hours' WHERE EXTRACT(HOUR FROM checkin_date)  = 0");
        $this->addSql("UPDATE reservations SET checkout_date = checkout_date + INTERVAL '11 hours' WHERE EXTRACT(HOUR FROM checkout_date) = 0");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE reservations ALTER checkin_date TYPE DATE');
        $this->addSql('ALTER TABLE reservations ALTER checkout_date TYPE DATE');
    }
}
