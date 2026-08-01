<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260531160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds rematch tracking fields on games';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE game ADD rematch_requests JSON DEFAULT '[]' NOT NULL");
        $this->addSql('ALTER TABLE game ADD rematch_game_id UUID DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game DROP rematch_requests');
        $this->addSql('ALTER TABLE game DROP rematch_game_id');
    }
}
