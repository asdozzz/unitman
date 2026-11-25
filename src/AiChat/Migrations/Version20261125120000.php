<?php

declare(strict_types=1);

namespace App\AiChat\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Сессии и сообщения AI-чата.
 *
 * `ai_chat_session` хранит сессию пользователя с провайдером, включая
 * provider-сессию (её идентификатор и срок жизни) и состояние архивации.
 * `ai_chat_message` — история сообщений сессии. Внешних ключей нет по
 * конвенции проекта: связи проверяются на уровне домена.
 */
final class Version20261125120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Таблицы AI-чата: ai_chat_session и ai_chat_message';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("create table if not exists ai_chat_session
            (
                id                         varchar(64)  not null,
                account_id                 varchar(128) not null,
                title                      varchar(256) not null default '',
                provider                   varchar(64)  not null,
                credential_id              varchar(128) null,
                model                      varchar(128) not null,
                provider_session_id        varchar(128) null,
                provider_session_expires_at integer     null,
                expires_at                 integer      not null,
                archived_at                integer      null,
                archive_reason            varchar(64)  null,
                created_at                 integer      not null,
                updated_at                 integer      not null,
                primary key (id)
            );");

        $this->addSql('create index if not exists ai_chat_session_account_updated_idx
            on ai_chat_session (account_id, updated_at desc);');

        $this->addSql('create index if not exists ai_chat_session_account_archived_idx
            on ai_chat_session (account_id, archived_at);');

        $this->addSql('create index if not exists ai_chat_session_expires_idx
            on ai_chat_session (expires_at);');

        $this->addSql('create index if not exists ai_chat_session_account_credential_idx
            on ai_chat_session (account_id, credential_id);');

        $this->addSql("create table if not exists ai_chat_message
            (
                id           varchar(64) not null,
                session_id   varchar(64) not null,
                seq          integer     not null,
                role         varchar(16) not null,
                content      text        null,
                tool_calls   jsonb       null,
                tool_call_id varchar(64) null,
                name         varchar(64) null,
                created_at   integer     not null,
                primary key (id)
            );");

        $this->addSql('create unique index if not exists ai_chat_message_session_seq_uq
            on ai_chat_message (session_id, seq);');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('drop table if exists ai_chat_message');
        $this->addSql('drop table if exists ai_chat_session');
    }
}
