<?php

declare(strict_types=1);

namespace App\OpenCode\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Жёсткая привязка workspace к раннеру (multi-runner).
 *
 * Workspace-ы OpenCode не имеют таблицы с `workspace_id` в качестве PK
 * (`opencode_workspace_session.id` — id сессии), поэтому физический FK не
 * добавляется. Подробности контракта: plans/runner-multi-runner.md.
 */
final class Version20261124121000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'OpenCode: таблица привязки workspace к раннерам (workspace_runner)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'create table if not exists workspace_runner ('
            . 'workspace_id varchar(64) not null primary key, '
            . 'runner_id varchar(128) not null, '
            . 'created_at integer not null'
            . ')'
        );

        $this->addSql(
            'create index if not exists workspace_runner_runner_idx '
            . 'on workspace_runner (runner_id)'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('drop index if exists workspace_runner_runner_idx');
        $this->addSql('drop table if exists workspace_runner');
    }
}
