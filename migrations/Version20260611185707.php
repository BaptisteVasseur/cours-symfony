<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260611185707 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE reservation_status_history DROP CONSTRAINT fk_3a5e46f7b83297e7');
        $this->addSql('ALTER TABLE reservation_status_history DROP CONSTRAINT fk_3a5e46f7828ad0a0');
        $this->addSql('DROP INDEX idx_3a5e46f7828ad0a0');
        $this->addSql('ALTER TABLE reservation_status_history ADD from_status VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE reservation_status_history ADD to_status VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE reservation_status_history ADD actor VARCHAR(10) DEFAULT NULL');
        $this->addSql('ALTER TABLE reservation_status_history ADD reason TEXT DEFAULT NULL');
        
        // Data migration
        $this->addSql('UPDATE reservation_status_history SET from_status = old_status, to_status = new_status');
        $this->addSql("UPDATE reservation_status_history h SET actor = CASE WHEN h.changed_by_id = r.guest_id THEN 'guest' ELSE 'host' END FROM booking r WHERE h.reservation_id = r.id");
        $this->addSql("UPDATE reservation_status_history SET actor = 'system' WHERE actor IS NULL");
        
        $this->addSql('ALTER TABLE reservation_status_history ALTER COLUMN to_status SET NOT NULL');
        
        $this->addSql('ALTER TABLE reservation_status_history DROP old_status');
        $this->addSql('ALTER TABLE reservation_status_history DROP new_status');
        $this->addSql('ALTER TABLE reservation_status_history DROP changed_by_id');
        $this->addSql('ALTER TABLE reservation_status_history ADD CONSTRAINT FK_3A5E46F7B83297E7 FOREIGN KEY (reservation_id) REFERENCES booking (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE reservation_status_history DROP CONSTRAINT FK_3A5E46F7B83297E7');
        $this->addSql('ALTER TABLE reservation_status_history ADD old_status VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE reservation_status_history ADD new_status VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE reservation_status_history ADD changed_by_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE reservation_status_history DROP from_status');
        $this->addSql('ALTER TABLE reservation_status_history DROP to_status');
        $this->addSql('ALTER TABLE reservation_status_history DROP actor');
        $this->addSql('ALTER TABLE reservation_status_history DROP reason');
        $this->addSql('ALTER TABLE reservation_status_history ADD CONSTRAINT fk_3a5e46f7b83297e7 FOREIGN KEY (reservation_id) REFERENCES booking (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE reservation_status_history ADD CONSTRAINT fk_3a5e46f7828ad0a0 FOREIGN KEY (changed_by_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_3a5e46f7828ad0a0 ON reservation_status_history (changed_by_id)');
    }
}
