<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260611103653 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE property_availability ADD source VARCHAR(20) DEFAULT \'manual\' NOT NULL');
        $this->addSql('ALTER TABLE property_availability ADD source_uid VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ALTER roles DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE property_availability DROP source');
        $this->addSql('ALTER TABLE property_availability DROP source_uid');
        $this->addSql('ALTER TABLE users ALTER roles SET DEFAULT \'[]\'');
    }
}
