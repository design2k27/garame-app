<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260424113000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds state version on games for sync/reconnect';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game ADD state_version INT DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game DROP state_version');
    }
}
