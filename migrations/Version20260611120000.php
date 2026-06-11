<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute calendar_token unique sur properties pour export iCal sécurisé';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE properties
            ADD COLUMN IF NOT EXISTS calendar_token VARCHAR(64) DEFAULT NULL
        SQL);

        // Génère un token pour les propriétés existantes (md5 x2 = 64 chars hex, sans extension)
        $this->addSql(<<<'SQL'
            UPDATE properties
            SET calendar_token = md5(random()::text || id::text) || md5(clock_timestamp()::text)
            WHERE calendar_token IS NULL
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE properties
            ALTER COLUMN calendar_token SET NOT NULL
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE properties
            ADD CONSTRAINT uq_properties_calendar_token UNIQUE (calendar_token)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE properties DROP CONSTRAINT IF EXISTS uq_properties_calendar_token');
        $this->addSql('ALTER TABLE properties DROP COLUMN IF EXISTS calendar_token');
    }
}
