<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260611205424 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE reservation_status_history RENAME TO booking_status_history');
        $this->addSql('ALTER TABLE booking_status_history RENAME COLUMN reservation_id TO booking_id');
        $this->addSql('ALTER TABLE booking_status_history DROP CONSTRAINT IF EXISTS fk_3a5e46f7b83297e7');
        $this->addSql('ALTER TABLE booking_status_history DROP CONSTRAINT IF EXISTS FK_3A5E46F7B83297E7');
        $this->addSql('DROP INDEX IF EXISTS idx_3a5e46f7828ad0a0');
        $this->addSql('DROP INDEX IF EXISTS idx_3a5e46f7b83297e7');
        $this->addSql('CREATE INDEX IDX_B405FC3E3301C60 ON booking_status_history (booking_id)');
        $this->addSql('ALTER TABLE booking_status_history ADD CONSTRAINT FK_B405FC3E3301C60 FOREIGN KEY (booking_id) REFERENCES booking (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE booking_status_history RENAME TO reservation_status_history');
        $this->addSql('ALTER TABLE reservation_status_history RENAME COLUMN booking_id TO reservation_id');
        $this->addSql('ALTER TABLE reservation_status_history DROP CONSTRAINT IF EXISTS FK_B405FC3E3301C60');
        $this->addSql('DROP INDEX IF EXISTS IDX_B405FC3E3301C60');
        $this->addSql('CREATE INDEX idx_3a5e46f7b83297e7 ON reservation_status_history (reservation_id)');
        $this->addSql('ALTER TABLE reservation_status_history ADD CONSTRAINT fk_3a5e46f7b83297e7 FOREIGN KEY (reservation_id) REFERENCES booking (id) ON DELETE CASCADE NOT DEFERRABLE');
    }
}
