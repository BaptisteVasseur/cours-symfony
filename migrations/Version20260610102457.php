<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260610102457 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'No-op: owner, owner_profiles, and properties.owner_id already exist in DB';
    }

    public function up(Schema $schema): void {}

    public function down(Schema $schema): void {}
}
