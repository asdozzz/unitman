<?php

declare(strict_types=1);

namespace App\OpenCode\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Expand-миграция M1: доводим новый workspace-агрегат до паритета с легаси.
 *
 * Только аддитивные изменения, схема легаси не трогается:
 *  - `opencode_workspace_turn` получает автора и реквизиты хода (паритет с
 *    `opencode_turn.author_account_id`/`credential_id`/`author_display_name`);
 *  - уникальность уведомлений нового агрегата становится `(turn_id, account_id)`,
 *    как в легаси: один ход может быть разослан нескольким участникам;
 *  - появляется `opencode_workspace_participant` с FK на новый агрегат и
 *    частичным уникальным индексом «ровно один owner»;
 *  - появляется `opencode_workspace_pending_update` (pending-обновление
 *    проекта/файлов) с FK на новый агрегат;
 *  - добавляется индекс под список workspace `(account_id, updated_at desc)`;
 *  - журнал перенумерации `seq` (только для целевой таблицы), чтобы обработка
 *    дублей `seq` была воспроизводимой и не затрагивала легаси.
 *
 * Все операции идемпотентны: повторный `up` не падает на уже существующих
 * колонках/таблицах/индексах, внешние ключи добавляются через проверку
 * `pg_constraint`.
 */
final class Version20261127150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'OpenCode: expand нового workspace-агрегата (авторство хода, участники, pending, индексы)';
    }

    public function up(Schema $schema): void
    {
        // 1. Авторство хода: паритет с opencode_turn.
        $this->addSql(
            "alter table opencode_workspace_turn
             add column if not exists author_account_id varchar(128) not null default ''"
        );
        $this->addSql(
            "alter table opencode_workspace_turn
             add column if not exists credential_id varchar(64) not null default ''"
        );
        $this->addSql(
            "alter table opencode_workspace_turn
             add column if not exists author_display_name varchar(256) not null default ''"
        );

        $this->addSql(
            'create index if not exists opencode_workspace_turn_author_idx
             on opencode_workspace_turn (author_account_id)'
        );

        // 2. Уведомления: получатель входит в ключ идемпотентности.
        $this->addSql('drop index if exists opencode_workspace_notification_turn_uq');
        $this->addSql(
            'create unique index if not exists opencode_workspace_notification_turn_recipient_uq
             on opencode_workspace_notification (turn_id, account_id)'
        );

        // 3. Участники workspace на собственном агрегате.
        $this->addSql(
            'create table if not exists opencode_workspace_participant (
                workspace_id          varchar(64)  not null,
                account_id            varchar(128) not null,
                role                  varchar(16)  not null,
                default_credential_id varchar(64)  null,
                default_model         varchar(128) null,
                invited_by            varchar(128) null,
                created_at            integer      not null,
                updated_at            integer      not null,
                primary key (workspace_id, account_id)
            )'
        );

        $this->addSql(
            'create index if not exists opencode_workspace_participant_account_idx
             on opencode_workspace_participant (account_id, workspace_id)'
        );

        $this->addSql(
            "create unique index if not exists opencode_workspace_participant_owner_uq
             on opencode_workspace_participant (workspace_id) where role = 'owner'"
        );

        $this->addSql(
            "do \$\$ begin
                if not exists (
                    select 1 from pg_constraint where conname = 'opencode_workspace_participant_workspace_fk'
                ) then
                    alter table opencode_workspace_participant
                        add constraint opencode_workspace_participant_workspace_fk
                        foreign key (workspace_id) references opencode_workspace_session (id) on delete cascade;
                end if;
             end \$\$"
        );

        // 4. Pending-обновление на собственном агрегате.
        $this->addSql(
            'create table if not exists opencode_workspace_pending_update (
                workspace_id varchar(64) not null primary key,
                workflow_id  varchar(64) not null,
                payload      jsonb       not null,
                created_at   integer     not null
            )'
        );

        $this->addSql(
            "do \$\$ begin
                if not exists (
                    select 1 from pg_constraint where conname = 'opencode_workspace_pending_update_workspace_fk'
                ) then
                    alter table opencode_workspace_pending_update
                        add constraint opencode_workspace_pending_update_workspace_fk
                        foreign key (workspace_id) references opencode_workspace_session (id) on delete cascade;
                end if;
             end \$\$"
        );

        // 5. Журнал перенумерации seq (только целевая таблица).
        $this->addSql(
            'create table if not exists opencode_workspace_migration_journal (
                workspace_id varchar(64)  not null,
                turn_id      varchar(64)  not null,
                old_seq      integer      not null,
                new_seq      integer      not null,
                created_at   integer      not null,
                primary key (workspace_id, turn_id)
            )'
        );

        // 6. Индекс под список workspace.
        $this->addSql(
            'create index if not exists opencode_workspace_session_account_updated_idx
             on opencode_workspace_session (account_id, updated_at desc)'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('drop index if exists opencode_workspace_session_account_updated_idx');

        $this->addSql('drop table if exists opencode_workspace_migration_journal');

        $this->addSql(
            'alter table opencode_workspace_pending_update
             drop constraint if exists opencode_workspace_pending_update_workspace_fk'
        );
        $this->addSql('drop table if exists opencode_workspace_pending_update');

        $this->addSql(
            'alter table opencode_workspace_participant
             drop constraint if exists opencode_workspace_participant_workspace_fk'
        );
        $this->addSql('drop index if exists opencode_workspace_participant_owner_uq');
        $this->addSql('drop index if exists opencode_workspace_participant_account_idx');
        $this->addSql('drop table if exists opencode_workspace_participant');

        // Перед возвратом уникальности по одному turn_id оставляем по одной
        // строке на ход: иначе индекс не создастся на разосланных уведомлениях.
        $this->addSql(
            'delete from opencode_workspace_notification a
             using opencode_workspace_notification b
             where a.turn_id = b.turn_id and a.id > b.id'
        );
        $this->addSql('drop index if exists opencode_workspace_notification_turn_recipient_uq');
        $this->addSql(
            'create unique index if not exists opencode_workspace_notification_turn_uq
             on opencode_workspace_notification (turn_id)'
        );

        $this->addSql('drop index if exists opencode_workspace_turn_author_idx');
        $this->addSql('alter table opencode_workspace_turn drop column if exists author_display_name');
        $this->addSql('alter table opencode_workspace_turn drop column if exists credential_id');
        $this->addSql('alter table opencode_workspace_turn drop column if exists author_account_id');
    }
}
