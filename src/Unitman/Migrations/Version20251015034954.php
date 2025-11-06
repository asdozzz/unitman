<?php

declare(strict_types=1);

namespace App\Unitman\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251015034954 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("create table IF NOT EXISTS project_webhook
            (
                id varchar not null primary key,
                project_id varchar not null,
                url varchar not null,
                active bit,
                deleted_at integer default null
            );
        ");

    }

    public function down(Schema $schema): void
    {
        $this->addSql('drop table if exists project_webhook');

    }
}
