<?php

declare(strict_types=1);

namespace App\OpenCode\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Совместный чат workspace: участники и авторство хода.
 *
 * Владелец остаётся в `opencode_session.account_id` и дублируется строкой
 * `owner` в `opencode_session_participant`: это позволяет проверять доступ
 * единым запросом, не теряя совместимости со старыми сессиями.
 *
 * `opencode_turn` получает автора и реквизиты, с которых списан ход. Ключ
 * здесь не хранится — только идентификаторы: расшифровка остаётся в Account.
 */
final class Version20261127130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'OpenCode: участники workspace и автор хода (opencode_session_participant, opencode_turn)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'create table if not exists opencode_session_participant (
                session_id varchar(64) not null,
                account_id varchar(128) not null,
                role varchar(16) not null,
                default_credential_id varchar(64) null,
                default_model varchar(128) null,
                invited_by varchar(128) null,
                created_at integer not null,
                updated_at integer not null,
                primary key (session_id, account_id)
            )'
        );

        $this->addSql(
            'create index if not exists opencode_session_participant_account_idx
             on opencode_session_participant (account_id, session_id)'
        );

        // Владелец у сессии ровно один: частичный уникальный индекс не даёт
        // добавить второго и не мешает остальным ролям.
        $this->addSql(
            "create unique index if not exists opencode_session_participant_owner_uq
             on opencode_session_participant (session_id) where role = 'owner'"
        );

        $this->addSql(
            'alter table opencode_session_participant
             add constraint opencode_session_participant_session_fk
             foreign key (session_id) references opencode_session (id) on delete cascade'
        );

        $this->addSql(
            "alter table opencode_turn
             add column if not exists author_account_id varchar(128) not null default ''"
        );
        $this->addSql(
            "alter table opencode_turn
             add column if not exists credential_id varchar(64) not null default ''"
        );
        $this->addSql(
            "alter table opencode_turn
             add column if not exists author_display_name varchar(256) not null default ''"
        );

        $this->addSql(
            'create index if not exists opencode_turn_author_idx
             on opencode_turn (author_account_id)'
        );

        // Существующие workspace: владелец становится участником, иначе после
        // включения проверок он потеряет доступ к собственной сессии.
        $this->addSql(
            "insert into opencode_session_participant
                (session_id, account_id, role, default_credential_id, default_model,
                 invited_by, created_at, updated_at)
             select s.id, s.account_id, 'owner', s.credential_id, s.model,
                    null, s.created_at, s.updated_at
             from opencode_session s
             where s.tip = 'workspace'
             on conflict (session_id, account_id) do nothing"
        );

        // Исторические ходы принадлежали владельцу сессии и списывались с его
        // реквизитов — это и есть корректный backfill авторства.
        $this->addSql(
            "update opencode_turn t
             set author_account_id = s.account_id,
                 credential_id = s.credential_id
             from opencode_session s
             where t.session_id = s.id
               and t.author_account_id = ''"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('drop index if exists opencode_turn_author_idx');
        $this->addSql('alter table opencode_turn drop column if exists author_display_name');
        $this->addSql('alter table opencode_turn drop column if exists credential_id');
        $this->addSql('alter table opencode_turn drop column if exists author_account_id');

        $this->addSql('alter table opencode_session_participant drop constraint if exists opencode_session_participant_session_fk');
        $this->addSql('drop index if exists opencode_session_participant_owner_uq');
        $this->addSql('drop index if exists opencode_session_participant_account_idx');
        $this->addSql('drop table if exists opencode_session_participant');
    }
}
