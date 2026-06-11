<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260611101849 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE property_availabilities (id UUID NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, reason VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, property_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_1389AEB549213EC ON property_availabilities (property_id)');
        $this->addSql('ALTER TABLE property_availabilities ADD CONSTRAINT FK_1389AEB549213EC FOREIGN KEY (property_id) REFERENCES properties (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE bookings ADD cancellation_reason TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE bookings ALTER status TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE bookings ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER INDEX idx_bookings_property_id RENAME TO IDX_7A853C35549213EC');
        $this->addSql('ALTER INDEX idx_bookings_traveler_id RENAME TO IDX_7A853C3559BBE8A3');
        $this->addSql('ALTER TABLE conversations ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER INDEX uniq_conversations_booking_id RENAME TO UNIQ_C2521BF13301C60');
        $this->addSql('ALTER TABLE messages ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER INDEX idx_messages_conversation_id RENAME TO IDX_DB021E969AC0396');
        $this->addSql('ALTER INDEX idx_messages_sender_id RENAME TO IDX_DB021E96F624B39D');
        $this->addSql('ALTER TABLE payments ALTER status TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE payments ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER INDEX idx_payments_booking_id RENAME TO IDX_65D29B323301C60');
        $this->addSql('ALTER INDEX idx_payments_user_id RENAME TO IDX_65D29B32A76ED395');
        $this->addSql('ALTER TABLE properties ADD instant_booking BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql('ALTER TABLE properties ALTER instant_booking DROP DEFAULT');
        $this->addSql('ALTER TABLE properties ADD calendar_token VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE properties ALTER status TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE properties ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_87C331C73363A255 ON properties (calendar_token)');
        $this->addSql('ALTER INDEX idx_properties_host_id RENAME TO IDX_87C331C71FB8D185');
        $this->addSql('ALTER INDEX idx_property_images_property_id RENAME TO IDX_9E68D116549213EC');
        $this->addSql('ALTER TABLE reviews ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER INDEX idx_reviews_booking_id RENAME TO IDX_6970EB0F3301C60');
        $this->addSql('ALTER INDEX idx_reviews_reviewer_id RENAME TO IDX_6970EB0F70574616');
        $this->addSql('ALTER INDEX idx_reviews_property_id RENAME TO IDX_6970EB0F549213EC');
        $this->addSql('ALTER INDEX uniq_user_preferences_user_id RENAME TO UNIQ_402A6F60A76ED395');
        $this->addSql('ALTER TABLE users ALTER role TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE users ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER INDEX uniq_users_email RENAME TO UNIQ_1483A5E9E7927C74');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE property_availabilities DROP CONSTRAINT FK_1389AEB549213EC');
        $this->addSql('DROP TABLE property_availabilities');
        $this->addSql('ALTER TABLE bookings DROP cancellation_reason');
        $this->addSql('ALTER TABLE bookings ALTER status TYPE VARCHAR(20)');
        $this->addSql('ALTER TABLE bookings ALTER created_at TYPE TIMESTAMP(0) WITH TIME ZONE');
        $this->addSql('ALTER INDEX idx_7a853c3559bbe8a3 RENAME TO idx_bookings_traveler_id');
        $this->addSql('ALTER INDEX idx_7a853c35549213ec RENAME TO idx_bookings_property_id');
        $this->addSql('ALTER TABLE conversations ALTER created_at TYPE TIMESTAMP(0) WITH TIME ZONE');
        $this->addSql('ALTER INDEX uniq_c2521bf13301c60 RENAME TO uniq_conversations_booking_id');
        $this->addSql('ALTER TABLE messages ALTER created_at TYPE TIMESTAMP(0) WITH TIME ZONE');
        $this->addSql('ALTER INDEX idx_db021e96f624b39d RENAME TO idx_messages_sender_id');
        $this->addSql('ALTER INDEX idx_db021e969ac0396 RENAME TO idx_messages_conversation_id');
        $this->addSql('ALTER TABLE payments ALTER status TYPE VARCHAR(20)');
        $this->addSql('ALTER TABLE payments ALTER created_at TYPE TIMESTAMP(0) WITH TIME ZONE');
        $this->addSql('ALTER INDEX idx_65d29b323301c60 RENAME TO idx_payments_booking_id');
        $this->addSql('ALTER INDEX idx_65d29b32a76ed395 RENAME TO idx_payments_user_id');
        $this->addSql('DROP INDEX UNIQ_87C331C73363A255');
        $this->addSql('ALTER TABLE properties DROP instant_booking');
        $this->addSql('ALTER TABLE properties DROP calendar_token');
        $this->addSql('ALTER TABLE properties ALTER status TYPE VARCHAR(20)');
        $this->addSql('ALTER TABLE properties ALTER created_at TYPE TIMESTAMP(0) WITH TIME ZONE');
        $this->addSql('ALTER INDEX idx_87c331c71fb8d185 RENAME TO idx_properties_host_id');
        $this->addSql('ALTER INDEX idx_9e68d116549213ec RENAME TO idx_property_images_property_id');
        $this->addSql('ALTER TABLE reviews ALTER created_at TYPE TIMESTAMP(0) WITH TIME ZONE');
        $this->addSql('ALTER INDEX idx_6970eb0f3301c60 RENAME TO idx_reviews_booking_id');
        $this->addSql('ALTER INDEX idx_6970eb0f70574616 RENAME TO idx_reviews_reviewer_id');
        $this->addSql('ALTER INDEX idx_6970eb0f549213ec RENAME TO idx_reviews_property_id');
        $this->addSql('ALTER INDEX uniq_402a6f60a76ed395 RENAME TO uniq_user_preferences_user_id');
        $this->addSql('ALTER TABLE users ALTER role TYPE VARCHAR(20)');
        $this->addSql('ALTER TABLE users ALTER created_at TYPE TIMESTAMP(0) WITH TIME ZONE');
        $this->addSql('ALTER INDEX uniq_1483a5e9e7927c74 RENAME TO uniq_users_email');
    }
}
