<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260611184205 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE availability_block (id UUID NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, reason VARCHAR(255) DEFAULT NULL, source VARCHAR(20) NOT NULL, external_uid VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, listing_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_47CAC266D4619D1A ON availability_block (listing_id)');
        $this->addSql('CREATE INDEX idx_block_listing_dates ON availability_block (listing_id, start_date, end_date)');
        $this->addSql('CREATE TABLE booking_history (id UUID NOT NULL, status VARCHAR(30) NOT NULL, comment TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, booking_id UUID NOT NULL, author_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_7D04356B3301C60 ON booking_history (booking_id)');
        $this->addSql('CREATE INDEX IDX_7D04356BF675F31B ON booking_history (author_id)');
        $this->addSql('ALTER TABLE availability_block ADD CONSTRAINT FK_47CAC266D4619D1A FOREIGN KEY (listing_id) REFERENCES listing (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE booking_history ADD CONSTRAINT FK_7D04356B3301C60 FOREIGN KEY (booking_id) REFERENCES booking (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE booking_history ADD CONSTRAINT FK_7D04356BF675F31B FOREIGN KEY (author_id) REFERENCES "user" (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE booking ADD cancelled_by_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE booking ADD CONSTRAINT FK_E00CEDDE187B2D12 FOREIGN KEY (cancelled_by_id) REFERENCES "user" (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_E00CEDDE187B2D12 ON booking (cancelled_by_id)');
        $this->addSql('CREATE INDEX idx_booking_listing_status_dates ON booking (listing_id, booking_status, check_in, check_out)');
        $this->addSql('ALTER TABLE listing ADD calendar_token VARCHAR(64) DEFAULT NULL');
        $this->addSql("UPDATE listing SET calendar_token = md5(random()::text || id::text) || md5(clock_timestamp()::text || id::text) WHERE calendar_token IS NULL");
        $this->addSql('ALTER TABLE listing ALTER COLUMN calendar_token SET NOT NULL');
        $this->addSql('ALTER TABLE listing ADD ical_import_url VARCHAR(1024) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_CB0048D43363A255 ON listing (calendar_token)');
        $this->addSql('ALTER INDEX uniq_8d93d649e7927c74 RENAME TO UNIQ_USER_EMAIL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE availability_block DROP CONSTRAINT FK_47CAC266D4619D1A');
        $this->addSql('ALTER TABLE booking_history DROP CONSTRAINT FK_7D04356B3301C60');
        $this->addSql('ALTER TABLE booking_history DROP CONSTRAINT FK_7D04356BF675F31B');
        $this->addSql('DROP TABLE availability_block');
        $this->addSql('DROP TABLE booking_history');
        $this->addSql('ALTER TABLE booking DROP CONSTRAINT FK_E00CEDDE187B2D12');
        $this->addSql('DROP INDEX IDX_E00CEDDE187B2D12');
        $this->addSql('DROP INDEX idx_booking_listing_status_dates');
        $this->addSql('ALTER TABLE booking DROP cancelled_by_id');
        $this->addSql('DROP INDEX UNIQ_CB0048D43363A255');
        $this->addSql('ALTER TABLE listing DROP calendar_token');
        $this->addSql('ALTER TABLE listing DROP ical_import_url');
        $this->addSql('ALTER INDEX uniq_user_email RENAME TO uniq_8d93d649e7927c74');
    }
}
