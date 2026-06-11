<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create favorites table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            CREATE TABLE favorites (
                id UUID NOT NULL DEFAULT gen_random_uuid(),
                user_id UUID NOT NULL,
                property_id UUID NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id),
                CONSTRAINT uq_favorite_user_property UNIQUE (user_id, property_id),
                CONSTRAINT fk_favorites_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT fk_favorites_property FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
            )
        ");
        $this->addSql("COMMENT ON COLUMN favorites.id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN favorites.user_id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN favorites.property_id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN favorites.created_at IS '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE favorites');
    }
}
