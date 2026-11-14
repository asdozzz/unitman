<?php

declare(strict_types=1);

namespace App\OpenCode\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261114100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Сессии и ходы OpenCode (история промптов, вопросов и ответов)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("create table IF NOT EXISTS opencode_session
            (
                id                  varchar(64)  not null
                    constraint opencode_session_pk primary key,
                account_id          varchar(128) not null,
                credential_id       varchar(64)  not null,
                title               varchar(256) not null default '',
                external_session_id varchar(256),
                created_at          integer      not null,
                updated_at          integer      not null
            );
        ");

        $this->addSql('create index IF NOT EXISTS opencode_session_account_idx on opencode_session (account_id);');
        $this->addSql('create index IF NOT EXISTS opencode_session_credential_idx on opencode_session (credential_id);');

        $this->addSql("create table IF NOT EXISTS opencode_turn
            (
                id           varchar(64) not null
                    constraint opencode_turn_pk primary key,
                session_id   varchar(64) not null,
                seq          integer     not null,
                workflow_id  varchar(64) not null,
                status       varchar(32) not null,
                request_text text        not null,
                answers_json text        not null default '[]',
                result_json  text,
                error_key    varchar(128),
                created_at   integer     not null,
                finished_at  integer
            );
        ");

        $this->addSql('create index IF NOT EXISTS opencode_turn_session_idx on opencode_turn (session_id, seq);');
        $this->addSql('create unique index IF NOT EXISTS opencode_turn_workflow_uq on opencode_turn (workflow_id);');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('drop table if exists opencode_turn');
        $this->addSql('drop table if exists opencode_session');
    }
}