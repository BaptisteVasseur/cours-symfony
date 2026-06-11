<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260610102357 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE owner (email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_CF60E67CE7927C74 ON owner (email)');
        $this->addSql('CREATE TABLE owner_profiles (first_name VARCHAR(100) DEFAULT NULL, last_name VARCHAR(100) DEFAULT NULL, birth_date DATE DEFAULT NULL, avatar_url TEXT DEFAULT NULL, bio TEXT DEFAULT NULL, identity_status VARCHAR(50) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, id UUID NOT NULL, owner_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_9DD03CF37E3C61F9 ON owner_profiles (owner_id)');
        $this->addSql('ALTER TABLE owner_profiles ADD CONSTRAINT FK_9DD03CF37E3C61F9 FOREIGN KEY (owner_id) REFERENCES owner (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE properties ADD owner_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE properties ADD CONSTRAINT FK_87C331C77E3C61F9 FOREIGN KEY (owner_id) REFERENCES owner (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_87C331C77E3C61F9 ON properties (owner_id)');
        $this->addSql('ALTER TABLE user_profiles ADD owner_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE user_profiles ADD CONSTRAINT FK_6BBD61307E3C61F9 FOREIGN KEY (owner_id) REFERENCES owner (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_6BBD61307E3C61F9 ON user_profiles (owner_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE owner_profiles DROP CONSTRAINT FK_9DD03CF37E3C61F9');
        $this->addSql('DROP TABLE owner');
        $this->addSql('DROP TABLE owner_profiles');
        $this->addSql('ALTER TABLE properties DROP CONSTRAINT FK_87C331C77E3C61F9');
        $this->addSql('DROP INDEX IDX_87C331C77E3C61F9');
        $this->addSql('ALTER TABLE properties DROP owner_id');
        $this->addSql('ALTER TABLE user_profiles DROP CONSTRAINT FK_6BBD61307E3C61F9');
        $this->addSql('DROP INDEX IDX_6BBD61307E3C61F9');
        $this->addSql('ALTER TABLE user_profiles DROP owner_id');
    }
}
