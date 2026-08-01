<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260424130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds realtime ack tracking fields on game_player';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game_player ADD last_acked_state_version INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE game_player ADD last_seen_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game_player DROP last_acked_state_version');
        $this->addSql('ALTER TABLE game_player DROP last_seen_at');
    }
}
