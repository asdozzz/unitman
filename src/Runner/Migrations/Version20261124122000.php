<?php

declare(strict_types=1);

namespace App\Runner\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Состояние сборки проекта на конкретном раннере (multi-runner).
 *
 * Проект собирается per-runner, поэтому ключ — составной `(runner_id, project_id)`.
 * Подробности контракта: plans/runner-multi-runner.md.
 */
final class Version20261124122000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Runner: состояние сборки проекта на раннере (project_build_state)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'create table if not exists project_build_state ('
            . 'runner_id varchar(128) not null '
            . 'constraint project_build_state_runner_fk references runner_state (id) on delete cascade, '
            . 'project_id varchar(64) not null, '
            . 'built_at integer not null, '
            . 'commit_sha varchar(64) default null, '
            . 'branch varchar(256) default null, '
            . 'primary key (runner_id, project_id)'
            . ')'
        );

        $this->addSql(
            'create index if not exists project_build_state_project_idx '
            . 'on project_build_state (project_id)'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('drop index if exists project_build_state_project_idx');
        $this->addSql('drop table if exists project_build_state');
    }
}
