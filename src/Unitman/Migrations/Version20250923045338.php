<?php

declare(strict_types=1);

namespace App\Unitman\Migrations;

use App\Utils\Service\MigrationWithContainer;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250923045338 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("DROP TABLE IF EXISTS ochered_unitov");
        $this->addSql("DROP TABLE IF EXISTS unit_runner_jobs");
        $this->addSql("DELETE FROM zadachi_dly_ocheredi where attempt = 10");
        $this->addSql("DELETE FROM unitman_event_store where payload->'headers'->>'__aggregate_root_type' = 'Unit'");
        $this->addSql("TRUNCATE TABLE spisok_unitov");


        $this->addSql("create table IF NOT EXISTS ochered_unitov
            (
                id serial primary key,
                unit_id varchar not null,
                last_update TIMESTAMP not null
            );
        ");

        $this->addSql("create table IF NOT EXISTS prozesi_unitov
            (
                id varchar not null primary key,
                unit_id varchar not null,
                payload jsonb
            );
        ");
    }

    public function down(Schema $schema): void
    {

    }
}
