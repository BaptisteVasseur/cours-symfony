<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611105300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les champs iCal export/import sur les logements et les blocs importes.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE availability_block ADD external_uid VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_avail_block_external_uid ON availability_block (property_id, external_uid)');
        $this->addSql('ALTER TABLE properties ADD ical_token VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE properties ADD external_ical_url VARCHAR(500) DEFAULT NULL');
        $this->addSql("UPDATE properties SET ical_token = md5(id::text) || md5(id::text || '-ical') WHERE ical_token IS NULL");
        $this->addSql('CREATE UNIQUE INDEX UNIQ_87C331C79F0C901D ON properties (ical_token)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_avail_block_external_uid');
        $this->addSql('ALTER TABLE availability_block DROP external_uid');
        $this->addSql('DROP INDEX UNIQ_87C331C79F0C901D');
        $this->addSql('ALTER TABLE properties DROP ical_token');
        $this->addSql('ALTER TABLE properties DROP external_ical_url');
    }
}
