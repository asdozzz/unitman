<?php

declare(strict_types=1);

namespace App\OpenCode\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Workspace-сессии OpenCode.
 *
 * opencode_session.tip           — тип сессии: unit (по умолчанию) или workspace;
 * opencode_workspace             — состояние workspace-сессии (ветка, статус, операция);
 * opencode_workspace_project     — снимок проектов workspace (порядок, canPush);
 * opencode_notification.session_type — тип владельца уведомления;
 * opencode_workspace_name_uq     — имя workspace уникально в рамках аккаунта.
 */
final class Version20261120150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Workspace OpenCode: тип сессии, таблицы workspace и проектов, тип уведомлений, уникальность имени';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("alter table opencode_session add column if not exists tip varchar(16) not null default 'unit'");

        $this->addSql(
            'create index if not exists opencode_session_account_tip_idx '
            . 'on opencode_session (account_id, tip, updated_at)'
        );

        $this->addSql(
            'create table if not exists opencode_workspace ('
            . 'session_id varchar(64) not null primary key, '
            . 'branch varchar(256) not null, '
            . 'status varchar(32) not null, '
            . 'read_only boolean not null default false, '
            . 'initial_prompt text not null, '
            . 'initial_prompt_started_at integer default null, '
            . 'error_key varchar(128) default null, '
            . 'operation varchar(32) not null, '
            . 'operation_workflow_id varchar(64) not null, '
            . 'operation_attempts integer not null default 0, '
            . 'next_attempt_at integer not null default 0, '
            . 'created_at integer not null, '
            . 'updated_at integer not null'
            . ')'
        );

        $this->addSql(
            'create index if not exists opencode_workspace_status_idx '
            . 'on opencode_workspace (status, next_attempt_at)'
        );

        $this->addSql(
            'create table if not exists opencode_workspace_project ('
            . 'session_id varchar(64) not null, '
            . 'project_id varchar(64) not null, '
            . 'project_name varchar(256) not null, '
            . 'project_code varchar(512) not null, '
            . 'can_push boolean not null default false, '
            . 'position_index integer not null default 0, '
            . 'created_at integer not null, '
            . 'primary key (session_id, project_id)'
            . ')'
        );

        $this->addSql(
            'create index if not exists opencode_workspace_project_session_idx '
            . 'on opencode_workspace_project (session_id, position_index)'
        );

        $this->addSql("alter table opencode_notification add column if not exists session_type varchar(16) not null default 'unit'");

        $this->addSql(
            'create unique index if not exists opencode_workspace_name_uq '
            . 'on opencode_session (account_id, title) '
            . "where tip = 'workspace'"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('drop index if exists opencode_workspace_name_uq');

        $this->addSql('alter table opencode_notification drop column if exists session_type');

        $this->addSql('drop index if exists opencode_workspace_project_session_idx');
        $this->addSql('drop table if exists opencode_workspace_project');

        $this->addSql('drop index if exists opencode_workspace_status_idx');
        $this->addSql('drop table if exists opencode_workspace');

        $this->addSql('drop index if exists opencode_session_account_tip_idx');
        $this->addSql('alter table opencode_session drop column if exists tip');
    }
}
