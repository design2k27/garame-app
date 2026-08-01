<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260424100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds matchmaking queue flag to games';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game ADD is_matchmaking_queue BOOLEAN DEFAULT FALSE NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game DROP is_matchmaking_queue');
    }
}
