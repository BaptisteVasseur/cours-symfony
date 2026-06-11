<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260609065908 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE admin_action (id UUID NOT NULL, action_type VARCHAR(50) NOT NULL, target_type VARCHAR(50) DEFAULT NULL, target_id UUID DEFAULT NULL, description TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, admin_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_1A04BD6B642B8210 ON admin_action (admin_id)');
        $this->addSql('CREATE TABLE amenity (id UUID NOT NULL, name VARCHAR(100) NOT NULL, icon TEXT DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE auth_provider (id UUID NOT NULL, provider VARCHAR(50) NOT NULL, provider_user_id TEXT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_B63EF41AA76ED395 ON auth_provider (user_id)');
        $this->addSql('CREATE TABLE booking (id UUID NOT NULL, check_in DATE NOT NULL, check_out DATE NOT NULL, guests_count INT DEFAULT NULL, nights_count INT DEFAULT NULL, base_amount NUMERIC(10, 2) NOT NULL, cleaning_fee NUMERIC(10, 2) DEFAULT NULL, service_fee NUMERIC(10, 2) DEFAULT NULL, taxes_amount NUMERIC(10, 2) DEFAULT NULL, total_amount NUMERIC(10, 2) NOT NULL, currency VARCHAR(3) DEFAULT NULL, booking_status VARCHAR(30) NOT NULL, cancellation_reason TEXT DEFAULT NULL, confirmed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, cancelled_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, listing_id UUID NOT NULL, guest_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_E00CEDDED4619D1A ON booking (listing_id)');
        $this->addSql('CREATE INDEX IDX_E00CEDDE9A4AA658 ON booking (guest_id)');
        $this->addSql('CREATE TABLE conversation (id UUID NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, booking_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8A8E26E93301C60 ON conversation (booking_id)');
        $this->addSql('CREATE TABLE conversation_participants (conversation_id UUID NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (conversation_id, user_id))');
        $this->addSql('CREATE INDEX IDX_21821ED39AC0396 ON conversation_participants (conversation_id)');
        $this->addSql('CREATE INDEX IDX_21821ED3A76ED395 ON conversation_participants (user_id)');
        $this->addSql('CREATE TABLE email_verification (id UUID NOT NULL, token TEXT NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, used BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_FE22358A76ED395 ON email_verification (user_id)');
        $this->addSql('CREATE TABLE listing (id UUID NOT NULL, title VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, property_type VARCHAR(50) DEFAULT NULL, room_type VARCHAR(50) DEFAULT NULL, max_guests INT DEFAULT NULL, bedrooms INT DEFAULT NULL, beds INT DEFAULT NULL, bathrooms INT DEFAULT NULL, channel VARCHAR(50) DEFAULT NULL, price_per_night NUMERIC(10, 2) NOT NULL, cleaning_fee NUMERIC(10, 2) DEFAULT NULL, service_fee NUMERIC(10, 2) DEFAULT NULL, currency VARCHAR(3) DEFAULT NULL, status VARCHAR(20) DEFAULT NULL, instant_booking BOOLEAN NOT NULL, cancellation_policy VARCHAR(50) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, host_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_CB0048D41FB8D185 ON listing (host_id)');
        $this->addSql('CREATE TABLE listing_amenities (listing_id UUID NOT NULL, amenity_id UUID NOT NULL, PRIMARY KEY (listing_id, amenity_id))');
        $this->addSql('CREATE INDEX IDX_5B46B037D4619D1A ON listing_amenities (listing_id)');
        $this->addSql('CREATE INDEX IDX_5B46B0379F9F1305 ON listing_amenities (amenity_id)');
        $this->addSql('CREATE TABLE listing_availability (id UUID NOT NULL, available_date DATE NOT NULL, is_available BOOLEAN NOT NULL, custom_price NUMERIC(10, 2) DEFAULT NULL, minimum_stay INT DEFAULT NULL, listing_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_BA858CBED4619D1A ON listing_availability (listing_id)');
        $this->addSql('CREATE TABLE listing_location (id UUID NOT NULL, country VARCHAR(100) NOT NULL, city VARCHAR(100) NOT NULL, state VARCHAR(100) DEFAULT NULL, address_line1 TEXT DEFAULT NULL, address_line2 TEXT DEFAULT NULL, postal_code VARCHAR(20) DEFAULT NULL, latitude NUMERIC(10, 7) DEFAULT NULL, longitude NUMERIC(11, 7) DEFAULT NULL, listing_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B8E2EBB1D4619D1A ON listing_location (listing_id)');
        $this->addSql('CREATE TABLE listing_photo (id UUID NOT NULL, image_url TEXT NOT NULL, position INT DEFAULT NULL, is_cover BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, listing_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_E2595C94D4619D1A ON listing_photo (listing_id)');
        $this->addSql('CREATE TABLE message (id UUID NOT NULL, message TEXT NOT NULL, attachment_url TEXT DEFAULT NULL, is_read BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, conversation_id UUID NOT NULL, sender_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_B6BD307F9AC0396 ON message (conversation_id)');
        $this->addSql('CREATE INDEX IDX_B6BD307FF624B39D ON message (sender_id)');
        $this->addSql('CREATE TABLE notification (id UUID NOT NULL, type VARCHAR(50) NOT NULL, channel VARCHAR(50) DEFAULT NULL, title VARCHAR(255) DEFAULT NULL, content TEXT DEFAULT NULL, is_read BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_BF5476CAA76ED395 ON notification (user_id)');
        $this->addSql('CREATE TABLE password_reset (id UUID NOT NULL, token TEXT NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, used BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_B1017252A76ED395 ON password_reset (user_id)');
        $this->addSql('CREATE TABLE payment (id UUID NOT NULL, stripe_payment_intent_id TEXT DEFAULT NULL, payment_method VARCHAR(50) DEFAULT NULL, amount NUMERIC(10, 2) NOT NULL, currency VARCHAR(3) NOT NULL, platform_fee NUMERIC(10, 2) DEFAULT NULL, host_payout NUMERIC(10, 2) DEFAULT NULL, payment_status VARCHAR(30) NOT NULL, paid_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, booking_id UUID NOT NULL, payer_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6D28840D3301C60 ON payment (booking_id)');
        $this->addSql('CREATE INDEX IDX_6D28840DC17AD9A9 ON payment (payer_id)');
        $this->addSql('CREATE TABLE payout (id UUID NOT NULL, amount NUMERIC(10, 2) NOT NULL, currency VARCHAR(3) NOT NULL, payout_status VARCHAR(30) NOT NULL, paid_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, host_id UUID NOT NULL, booking_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_4E2EA9021FB8D185 ON payout (host_id)');
        $this->addSql('CREATE INDEX IDX_4E2EA9023301C60 ON payout (booking_id)');
        $this->addSql('CREATE TABLE refresh_token (id UUID NOT NULL, token_hash TEXT NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, revoked BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_C74F2195A76ED395 ON refresh_token (user_id)');
        $this->addSql('CREATE TABLE report (id UUID NOT NULL, reason TEXT NOT NULL, report_status VARCHAR(30) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, reporter_id UUID NOT NULL, listing_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_C42F7784E1CFE6F5 ON report (reporter_id)');
        $this->addSql('CREATE INDEX IDX_C42F7784D4619D1A ON report (listing_id)');
        $this->addSql('CREATE TABLE review (id UUID NOT NULL, rating_overall INT NOT NULL, rating_cleanliness INT DEFAULT NULL, rating_communication INT DEFAULT NULL, rating_location INT DEFAULT NULL, rating_accuracy INT DEFAULT NULL, comment TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, booking_id UUID NOT NULL, listing_id UUID NOT NULL, reviewer_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_794381C63301C60 ON review (booking_id)');
        $this->addSql('CREATE INDEX IDX_794381C6D4619D1A ON review (listing_id)');
        $this->addSql('CREATE INDEX IDX_794381C670574616 ON review (reviewer_id)');
        $this->addSql('CREATE TABLE review_photo (id UUID NOT NULL, image_url TEXT NOT NULL, review_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_739A8033E2E969B ON review_photo (review_id)');
        $this->addSql('CREATE TABLE "user" (id UUID NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, email VARCHAR(255) NOT NULL, phone VARCHAR(20) DEFAULT NULL, password_hash TEXT NOT NULL, profile_picture TEXT DEFAULT NULL, biography TEXT DEFAULT NULL, preferred_language VARCHAR(10) DEFAULT NULL, preferred_currency VARCHAR(3) DEFAULT NULL, role VARCHAR(20) DEFAULT NULL, token VARCHAR(255) DEFAULT NULL, email_verified BOOLEAN NOT NULL, phone_verified BOOLEAN NOT NULL, identity_verified BOOLEAN NOT NULL, status VARCHAR(20) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649E7927C74 ON "user" (email)');
        $this->addSql('CREATE TABLE user_identity (id UUID NOT NULL, document_type VARCHAR(50) DEFAULT NULL, document_front TEXT DEFAULT NULL, document_back TEXT DEFAULT NULL, selfie_photo TEXT DEFAULT NULL, verification_status VARCHAR(30) DEFAULT NULL, verified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8A180DC4A76ED395 ON user_identity (user_id)');
        $this->addSql('CREATE TABLE wishlist (id UUID NOT NULL, name VARCHAR(100) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_9CE12A31A76ED395 ON wishlist (user_id)');
        $this->addSql('CREATE TABLE wishlist_items (wishlist_id UUID NOT NULL, listing_id UUID NOT NULL, PRIMARY KEY (wishlist_id, listing_id))');
        $this->addSql('CREATE INDEX IDX_B5BB81B5FB8E54CD ON wishlist_items (wishlist_id)');
        $this->addSql('CREATE INDEX IDX_B5BB81B5D4619D1A ON wishlist_items (listing_id)');
        $this->addSql('ALTER TABLE admin_action ADD CONSTRAINT FK_1A04BD6B642B8210 FOREIGN KEY (admin_id) REFERENCES "user" (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE auth_provider ADD CONSTRAINT FK_B63EF41AA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE booking ADD CONSTRAINT FK_E00CEDDED4619D1A FOREIGN KEY (listing_id) REFERENCES listing (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE booking ADD CONSTRAINT FK_E00CEDDE9A4AA658 FOREIGN KEY (guest_id) REFERENCES "user" (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE conversation ADD CONSTRAINT FK_8A8E26E93301C60 FOREIGN KEY (booking_id) REFERENCES booking (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE conversation_participants ADD CONSTRAINT FK_21821ED39AC0396 FOREIGN KEY (conversation_id) REFERENCES conversation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE conversation_participants ADD CONSTRAINT FK_21821ED3A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE email_verification ADD CONSTRAINT FK_FE22358A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE listing ADD CONSTRAINT FK_CB0048D41FB8D185 FOREIGN KEY (host_id) REFERENCES "user" (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE listing_amenities ADD CONSTRAINT FK_5B46B037D4619D1A FOREIGN KEY (listing_id) REFERENCES listing (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE listing_amenities ADD CONSTRAINT FK_5B46B0379F9F1305 FOREIGN KEY (amenity_id) REFERENCES amenity (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE listing_availability ADD CONSTRAINT FK_BA858CBED4619D1A FOREIGN KEY (listing_id) REFERENCES listing (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE listing_location ADD CONSTRAINT FK_B8E2EBB1D4619D1A FOREIGN KEY (listing_id) REFERENCES listing (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE listing_photo ADD CONSTRAINT FK_E2595C94D4619D1A FOREIGN KEY (listing_id) REFERENCES listing (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307F9AC0396 FOREIGN KEY (conversation_id) REFERENCES conversation (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307FF624B39D FOREIGN KEY (sender_id) REFERENCES "user" (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE password_reset ADD CONSTRAINT FK_B1017252A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840D3301C60 FOREIGN KEY (booking_id) REFERENCES booking (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840DC17AD9A9 FOREIGN KEY (payer_id) REFERENCES "user" (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE payout ADD CONSTRAINT FK_4E2EA9021FB8D185 FOREIGN KEY (host_id) REFERENCES "user" (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE payout ADD CONSTRAINT FK_4E2EA9023301C60 FOREIGN KEY (booking_id) REFERENCES booking (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE refresh_token ADD CONSTRAINT FK_C74F2195A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE report ADD CONSTRAINT FK_C42F7784E1CFE6F5 FOREIGN KEY (reporter_id) REFERENCES "user" (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE report ADD CONSTRAINT FK_C42F7784D4619D1A FOREIGN KEY (listing_id) REFERENCES listing (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE review ADD CONSTRAINT FK_794381C63301C60 FOREIGN KEY (booking_id) REFERENCES booking (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE review ADD CONSTRAINT FK_794381C6D4619D1A FOREIGN KEY (listing_id) REFERENCES listing (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE review ADD CONSTRAINT FK_794381C670574616 FOREIGN KEY (reviewer_id) REFERENCES "user" (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE review_photo ADD CONSTRAINT FK_739A8033E2E969B FOREIGN KEY (review_id) REFERENCES review (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE user_identity ADD CONSTRAINT FK_8A180DC4A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE wishlist ADD CONSTRAINT FK_9CE12A31A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE wishlist_items ADD CONSTRAINT FK_B5BB81B5FB8E54CD FOREIGN KEY (wishlist_id) REFERENCES wishlist (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE wishlist_items ADD CONSTRAINT FK_B5BB81B5D4619D1A FOREIGN KEY (listing_id) REFERENCES listing (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE admin_action DROP CONSTRAINT FK_1A04BD6B642B8210');
        $this->addSql('ALTER TABLE auth_provider DROP CONSTRAINT FK_B63EF41AA76ED395');
        $this->addSql('ALTER TABLE booking DROP CONSTRAINT FK_E00CEDDED4619D1A');
        $this->addSql('ALTER TABLE booking DROP CONSTRAINT FK_E00CEDDE9A4AA658');
        $this->addSql('ALTER TABLE conversation DROP CONSTRAINT FK_8A8E26E93301C60');
        $this->addSql('ALTER TABLE conversation_participants DROP CONSTRAINT FK_21821ED39AC0396');
        $this->addSql('ALTER TABLE conversation_participants DROP CONSTRAINT FK_21821ED3A76ED395');
        $this->addSql('ALTER TABLE email_verification DROP CONSTRAINT FK_FE22358A76ED395');
        $this->addSql('ALTER TABLE listing DROP CONSTRAINT FK_CB0048D41FB8D185');
        $this->addSql('ALTER TABLE listing_amenities DROP CONSTRAINT FK_5B46B037D4619D1A');
        $this->addSql('ALTER TABLE listing_amenities DROP CONSTRAINT FK_5B46B0379F9F1305');
        $this->addSql('ALTER TABLE listing_availability DROP CONSTRAINT FK_BA858CBED4619D1A');
        $this->addSql('ALTER TABLE listing_location DROP CONSTRAINT FK_B8E2EBB1D4619D1A');
        $this->addSql('ALTER TABLE listing_photo DROP CONSTRAINT FK_E2595C94D4619D1A');
        $this->addSql('ALTER TABLE message DROP CONSTRAINT FK_B6BD307F9AC0396');
        $this->addSql('ALTER TABLE message DROP CONSTRAINT FK_B6BD307FF624B39D');
        $this->addSql('ALTER TABLE notification DROP CONSTRAINT FK_BF5476CAA76ED395');
        $this->addSql('ALTER TABLE password_reset DROP CONSTRAINT FK_B1017252A76ED395');
        $this->addSql('ALTER TABLE payment DROP CONSTRAINT FK_6D28840D3301C60');
        $this->addSql('ALTER TABLE payment DROP CONSTRAINT FK_6D28840DC17AD9A9');
        $this->addSql('ALTER TABLE payout DROP CONSTRAINT FK_4E2EA9021FB8D185');
        $this->addSql('ALTER TABLE payout DROP CONSTRAINT FK_4E2EA9023301C60');
        $this->addSql('ALTER TABLE refresh_token DROP CONSTRAINT FK_C74F2195A76ED395');
        $this->addSql('ALTER TABLE report DROP CONSTRAINT FK_C42F7784E1CFE6F5');
        $this->addSql('ALTER TABLE report DROP CONSTRAINT FK_C42F7784D4619D1A');
        $this->addSql('ALTER TABLE review DROP CONSTRAINT FK_794381C63301C60');
        $this->addSql('ALTER TABLE review DROP CONSTRAINT FK_794381C6D4619D1A');
        $this->addSql('ALTER TABLE review DROP CONSTRAINT FK_794381C670574616');
        $this->addSql('ALTER TABLE review_photo DROP CONSTRAINT FK_739A8033E2E969B');
        $this->addSql('ALTER TABLE user_identity DROP CONSTRAINT FK_8A180DC4A76ED395');
        $this->addSql('ALTER TABLE wishlist DROP CONSTRAINT FK_9CE12A31A76ED395');
        $this->addSql('ALTER TABLE wishlist_items DROP CONSTRAINT FK_B5BB81B5FB8E54CD');
        $this->addSql('ALTER TABLE wishlist_items DROP CONSTRAINT FK_B5BB81B5D4619D1A');
        $this->addSql('DROP TABLE admin_action');
        $this->addSql('DROP TABLE amenity');
        $this->addSql('DROP TABLE auth_provider');
        $this->addSql('DROP TABLE booking');
        $this->addSql('DROP TABLE conversation');
        $this->addSql('DROP TABLE conversation_participants');
        $this->addSql('DROP TABLE email_verification');
        $this->addSql('DROP TABLE listing');
        $this->addSql('DROP TABLE listing_amenities');
        $this->addSql('DROP TABLE listing_availability');
        $this->addSql('DROP TABLE listing_location');
        $this->addSql('DROP TABLE listing_photo');
        $this->addSql('DROP TABLE message');
        $this->addSql('DROP TABLE notification');
        $this->addSql('DROP TABLE password_reset');
        $this->addSql('DROP TABLE payment');
        $this->addSql('DROP TABLE payout');
        $this->addSql('DROP TABLE refresh_token');
        $this->addSql('DROP TABLE report');
        $this->addSql('DROP TABLE review');
        $this->addSql('DROP TABLE review_photo');
        $this->addSql('DROP TABLE "user"');
        $this->addSql('DROP TABLE user_identity');
        $this->addSql('DROP TABLE wishlist');
        $this->addSql('DROP TABLE wishlist_items');
    }
}
