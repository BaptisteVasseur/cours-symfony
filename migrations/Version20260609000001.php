<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260609000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial schema: users, properties, bookings, payments, reviews, conversations, messages, user_preferences';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS "user" CASCADE');

        $this->addSql('CREATE TABLE users (
            id UUID NOT NULL,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            email VARCHAR(180) NOT NULL,
            password_hash TEXT NOT NULL,
            phone VARCHAR(20) DEFAULT NULL,
            role VARCHAR(20) NOT NULL,
            profile_picture_url TEXT DEFAULT NULL,
            created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_users_email ON users (email)');

        $this->addSql('CREATE TABLE properties (
            id UUID NOT NULL,
            host_id UUID NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT NOT NULL,
            address TEXT NOT NULL,
            city VARCHAR(100) NOT NULL,
            country VARCHAR(100) NOT NULL,
            latitude NUMERIC(10, 7) DEFAULT NULL,
            longitude NUMERIC(10, 7) DEFAULT NULL,
            price_per_night NUMERIC(10, 2) NOT NULL,
            max_guests INT NOT NULL,
            status VARCHAR(20) NOT NULL,
            created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX IDX_properties_host_id ON properties (host_id)');
        $this->addSql('ALTER TABLE properties ADD CONSTRAINT FK_properties_host FOREIGN KEY (host_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE property_images (
            id UUID NOT NULL,
            property_id UUID NOT NULL,
            image_url TEXT NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX IDX_property_images_property_id ON property_images (property_id)');
        $this->addSql('ALTER TABLE property_images ADD CONSTRAINT FK_property_images_property FOREIGN KEY (property_id) REFERENCES properties (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE bookings (
            id UUID NOT NULL,
            property_id UUID NOT NULL,
            traveler_id UUID NOT NULL,
            check_in DATE NOT NULL,
            check_out DATE NOT NULL,
            guests_count INT NOT NULL,
            total_price NUMERIC(10, 2) NOT NULL,
            status VARCHAR(20) NOT NULL,
            created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX IDX_bookings_property_id ON bookings (property_id)');
        $this->addSql('CREATE INDEX IDX_bookings_traveler_id ON bookings (traveler_id)');
        $this->addSql('ALTER TABLE bookings ADD CONSTRAINT FK_bookings_property FOREIGN KEY (property_id) REFERENCES properties (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE bookings ADD CONSTRAINT FK_bookings_traveler FOREIGN KEY (traveler_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE payments (
            id UUID NOT NULL,
            booking_id UUID NOT NULL,
            user_id UUID NOT NULL,
            amount NUMERIC(10, 2) NOT NULL,
            currency VARCHAR(3) NOT NULL,
            status VARCHAR(20) NOT NULL,
            provider VARCHAR(100) NOT NULL,
            created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX IDX_payments_booking_id ON payments (booking_id)');
        $this->addSql('CREATE INDEX IDX_payments_user_id ON payments (user_id)');
        $this->addSql('ALTER TABLE payments ADD CONSTRAINT FK_payments_booking FOREIGN KEY (booking_id) REFERENCES bookings (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE payments ADD CONSTRAINT FK_payments_user FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE reviews (
            id UUID NOT NULL,
            booking_id UUID NOT NULL,
            reviewer_id UUID NOT NULL,
            property_id UUID NOT NULL,
            rating INT NOT NULL,
            comment TEXT NOT NULL,
            created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX IDX_reviews_booking_id ON reviews (booking_id)');
        $this->addSql('CREATE INDEX IDX_reviews_reviewer_id ON reviews (reviewer_id)');
        $this->addSql('CREATE INDEX IDX_reviews_property_id ON reviews (property_id)');
        $this->addSql('ALTER TABLE reviews ADD CONSTRAINT FK_reviews_booking FOREIGN KEY (booking_id) REFERENCES bookings (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE reviews ADD CONSTRAINT FK_reviews_reviewer FOREIGN KEY (reviewer_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE reviews ADD CONSTRAINT FK_reviews_property FOREIGN KEY (property_id) REFERENCES properties (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE conversations (
            id UUID NOT NULL,
            booking_id UUID NOT NULL,
            created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_conversations_booking_id ON conversations (booking_id)');
        $this->addSql('ALTER TABLE conversations ADD CONSTRAINT FK_conversations_booking FOREIGN KEY (booking_id) REFERENCES bookings (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE messages (
            id UUID NOT NULL,
            conversation_id UUID NOT NULL,
            sender_id UUID NOT NULL,
            content TEXT NOT NULL,
            created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX IDX_messages_conversation_id ON messages (conversation_id)');
        $this->addSql('CREATE INDEX IDX_messages_sender_id ON messages (sender_id)');
        $this->addSql('ALTER TABLE messages ADD CONSTRAINT FK_messages_conversation FOREIGN KEY (conversation_id) REFERENCES conversations (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE messages ADD CONSTRAINT FK_messages_sender FOREIGN KEY (sender_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE user_preferences (
            id UUID NOT NULL,
            user_id UUID NOT NULL,
            language VARCHAR(10) NOT NULL,
            currency VARCHAR(3) NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_user_preferences_user_id ON user_preferences (user_id)');
        $this->addSql('ALTER TABLE user_preferences ADD CONSTRAINT FK_user_preferences_user FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS messages CASCADE');
        $this->addSql('DROP TABLE IF EXISTS conversations CASCADE');
        $this->addSql('DROP TABLE IF EXISTS reviews CASCADE');
        $this->addSql('DROP TABLE IF EXISTS payments CASCADE');
        $this->addSql('DROP TABLE IF EXISTS bookings CASCADE');
        $this->addSql('DROP TABLE IF EXISTS property_images CASCADE');
        $this->addSql('DROP TABLE IF EXISTS properties CASCADE');
        $this->addSql('DROP TABLE IF EXISTS user_preferences CASCADE');
        $this->addSql('DROP TABLE IF EXISTS users CASCADE');
    }
}
