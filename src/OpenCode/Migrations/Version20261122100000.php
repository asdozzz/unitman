<?php

declare(strict_types=1);

namespace App\OpenCode\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Разделение Workspace и UnitSession на независимые наборы таблиц.
 *
 * Новые таблицы не связаны со старыми `opencode_session`/`opencode_turn`/
 * `opencode_notification`: у workspace и unit-сессии свои ходы и уведомления.
 * Старые таблицы на этом этапе остаются нетронутыми (expand-фаза), удаление —
 * отдельной миграцией после backfill и проверки.
 */
final class Version20261122100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'OpenCode: независимые таблицы UnitSession и Workspace';
    }

    public function up(Schema $schema): void
    {
        // ─── UnitSession ────────────────────────────────────────────────────
        $this->addSql(
            'create table if not exists opencode_unit_session ('
            . 'id varchar(64) not null primary key, '
            . 'account_id varchar(128) not null, '
            . 'credential_id varchar(64) not null, '
            . 'title varchar(256) not null default \'\', '
            . 'external_session_id varchar(256) null, '
            . 'unit_id varchar(64) not null, '
            . 'unit_project_id varchar(64) not null, '
            . 'archived_at integer null, '
            . 'archive_reason varchar(64) null, '
            . 'created_at integer not null, '
            . 'updated_at integer not null'
            . ')'
        );

        $this->addSql(
            'create unique index if not exists opencode_unit_session_active_unit_uq '
            . 'on opencode_unit_session (unit_id) where archived_at is null'
        );

        $this->addSql(
            'create index if not exists opencode_unit_session_account_idx '
            . 'on opencode_unit_session (account_id, updated_at)'
        );

        $this->addSql(
            'create table if not exists opencode_unit_session_turn ('
            . 'id varchar(64) not null primary key, '
            . 'session_id varchar(64) not null, '
            . 'seq integer not null, '
            . 'workflow_id varchar(64) not null, '
            . 'status varchar(32) not null, '
            . 'request_text text not null, '
            . 'answers_json text not null default \'[]\', '
            . 'result_json text null, '
            . 'error_key varchar(128) null, '
            . 'created_at integer not null, '
            . 'finished_at integer null'
            . ')'
        );

        $this->addSql(
            'create unique index if not exists opencode_unit_session_turn_seq_uq '
            . 'on opencode_unit_session_turn (session_id, seq)'
        );

        $this->addSql(
            'create unique index if not exists opencode_unit_session_turn_workflow_uq '
            . 'on opencode_unit_session_turn (workflow_id)'
        );

        $this->addSql(
            'create index if not exists opencode_unit_session_turn_status_idx '
            . 'on opencode_unit_session_turn (status, created_at)'
        );

        $this->addSql(
            'create table if not exists opencode_unit_session_notification ('
            . 'id varchar(64) not null primary key, '
            . 'account_id varchar(128) not null, '
            . 'session_id varchar(64) not null, '
            . 'turn_id varchar(64) not null, '
            . 'session_title varchar(256) not null default \'\', '
            . 'status varchar(32) not null, '
            . 'kind varchar(32) null, '
            . 'created_at integer not null, '
            . 'read_at integer null'
            . ')'
        );

        $this->addSql(
            'create unique index if not exists opencode_unit_session_notification_turn_uq '
            . 'on opencode_unit_session_notification (turn_id)'
        );

        $this->addSql(
            'create index if not exists opencode_unit_session_notification_unread_idx '
            . 'on opencode_unit_session_notification (account_id, read_at)'
        );

        // ─── Workspace ──────────────────────────────────────────────────────
        $this->addSql(
            'create table if not exists opencode_workspace_session ('
            . 'id varchar(64) not null primary key, '
            . 'account_id varchar(128) not null, '
            . 'credential_id varchar(64) not null, '
            . 'name varchar(256) not null, '
            . 'branch varchar(256) not null, '
            . 'status varchar(32) not null, '
            . 'read_only boolean not null default false, '
            . 'initial_prompt text not null, '
            . 'initial_prompt_started_at integer null, '
            . 'error_key varchar(128) null, '
            . 'operation varchar(32) not null, '
            . 'operation_workflow_id varchar(64) not null, '
            . 'operation_attempts integer not null default 0, '
            . 'next_attempt_at integer not null default 0, '
            . 'external_session_id varchar(256) null, '
            . 'archived_at integer null, '
            . 'archive_reason varchar(64) null, '
            . 'created_at integer not null, '
            . 'updated_at integer not null'
            . ')'
        );

        $this->addSql(
            'create unique index if not exists opencode_workspace_session_account_name_uq '
            . 'on opencode_workspace_session (account_id, name)'
        );

        $this->addSql(
            'create index if not exists opencode_workspace_session_status_idx '
            . 'on opencode_workspace_session (status, next_attempt_at)'
        );

        $this->addSql(
            'create table if not exists opencode_workspace_session_project ('
            . 'workspace_id varchar(64) not null, '
            . 'project_id varchar(64) not null, '
            . 'project_name varchar(256) not null, '
            . 'project_code varchar(512) not null, '
            . 'main_branch varchar(256) not null default \'\', '
            . 'branch varchar(256) not null default \'\', '
            . 'can_push boolean not null default false, '
            . 'position_index integer not null default 0, '
            . 'created_at integer not null, '
            . 'primary key (workspace_id, project_id)'
            . ')'
        );

        $this->addSql(
            'create index if not exists opencode_workspace_session_project_idx '
            . 'on opencode_workspace_session_project (workspace_id, position_index)'
        );

        $this->addSql(
            'create table if not exists opencode_workspace_turn ('
            . 'id varchar(64) not null primary key, '
            . 'workspace_id varchar(64) not null, '
            . 'seq integer not null, '
            . 'workflow_id varchar(64) not null, '
            . 'status varchar(32) not null, '
            . 'request_text text not null, '
            . 'answers_json text not null default \'[]\', '
            . 'result_json text null, '
            . 'error_key varchar(128) null, '
            . 'created_at integer not null, '
            . 'finished_at integer null'
            . ')'
        );

        $this->addSql(
            'create unique index if not exists opencode_workspace_turn_seq_uq '
            . 'on opencode_workspace_turn (workspace_id, seq)'
        );

        $this->addSql(
            'create unique index if not exists opencode_workspace_turn_workflow_uq '
            . 'on opencode_workspace_turn (workflow_id)'
        );

        $this->addSql(
            'create index if not exists opencode_workspace_turn_status_idx '
            . 'on opencode_workspace_turn (status, created_at)'
        );

        $this->addSql(
            'create table if not exists opencode_workspace_notification ('
            . 'id varchar(64) not null primary key, '
            . 'account_id varchar(128) not null, '
            . 'workspace_id varchar(64) not null, '
            . 'turn_id varchar(64) not null, '
            . 'workspace_name varchar(256) not null default \'\', '
            . 'status varchar(32) not null, '
            . 'kind varchar(32) null, '
            . 'created_at integer not null, '
            . 'read_at integer null'
            . ')'
        );

        $this->addSql(
            'create unique index if not exists opencode_workspace_notification_turn_uq '
            . 'on opencode_workspace_notification (turn_id)'
        );

        $this->addSql(
            'create index if not exists opencode_workspace_notification_unread_idx '
            . 'on opencode_workspace_notification (account_id, read_at)'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('drop table if exists opencode_workspace_notification');
        $this->addSql('drop table if exists opencode_workspace_turn');
        $this->addSql('drop table if exists opencode_workspace_session_project');
        $this->addSql('drop table if exists opencode_workspace_session');
        $this->addSql('drop table if exists opencode_unit_session_notification');
        $this->addSql('drop table if exists opencode_unit_session_turn');
        $this->addSql('drop table if exists opencode_unit_session');
    }
}
