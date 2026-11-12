<?php

declare(strict_types=1);

namespace App\BackgroundJob\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261112000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Таблица background_jobs для реестра фоновых сервисов RoadRunner';
    }

    public function up(Schema $schema): void
    {
        // Раньше таблица создавалась в рантайме из JobRepository::init() на чистом Postgres
        // (serial). Теперь схема управляется миграцией, чтобы не зависеть от порядка
        // запуска сервиса и не выполнять DDL на каждом старте.
        $this->addSql('create table IF NOT EXISTS background_jobs
            (
                id     serial constraint background_jobs_pk primary key,
                name   varchar not null,
                run_id varchar,
                status int
            );
        ');

        // Имя задачи — естественный ключ: JobRepository::start() делает update-or-insert
        // по name, при гонке двух стартов без уникального индекса появлялись дубли.
        $this->addSql('create unique index IF NOT EXISTS background_jobs_name_uq on background_jobs (name);');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('drop table if exists background_jobs');
    }
}
