<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260611075428 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE user_favorites (user_id UUID NOT NULL, property_id UUID NOT NULL, PRIMARY KEY (user_id, property_id))');
        $this->addSql('CREATE INDEX IDX_E489ED11A76ED395 ON user_favorites (user_id)');
        $this->addSql('CREATE INDEX IDX_E489ED11549213EC ON user_favorites (property_id)');
        $this->addSql('ALTER TABLE user_favorites ADD CONSTRAINT FK_E489ED11A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_favorites ADD CONSTRAINT FK_E489ED11549213EC FOREIGN KEY (property_id) REFERENCES properties (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_favorites DROP CONSTRAINT FK_E489ED11A76ED395');
        $this->addSql('ALTER TABLE user_favorites DROP CONSTRAINT FK_E489ED11549213EC');
        $this->addSql('DROP TABLE user_favorites');
    }
}
