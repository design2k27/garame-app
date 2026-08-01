<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260423140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds ranking credits to users';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD credits INT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP credits');
    }
}
