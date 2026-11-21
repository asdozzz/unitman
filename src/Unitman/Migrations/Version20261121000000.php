<?php

declare(strict_types=1);

namespace App\Unitman\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Личные токены хранилищ: событийный журнал и read-модель проекции.
 */
final class Version20261121000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'unitman: personal storage tokens event store and read model';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("create table IF NOT EXISTS unitman_lichniy_token_event_store
        (
            id                serial  not null
                constraint unitman_lichniy_token_event_store_pk
                    primary key,
            event_id          varchar not null,
            aggregate_root_id varchar not null,
            version           integer not null,
            payload           jsonb   not null,
            constraint unitman_lichniy_token_reconstitution
                unique (aggregate_root_id, version)
        );");

        $this->addSql("create table IF NOT EXISTS lichniy_token
        (
            id               varchar(128) not null
                constraint lichniy_token_pk primary key,
            account_id       varchar(128) not null,
            repo_id          varchar(128) not null,
            nazvanie         varchar(255) not null,
            token_cipher     text         not null,
            token_hint       varchar(64)  not null,
            obyom_prav       varchar(32)  not null,
            proekty_s_pushom jsonb        not null default '[]',
            provereno_v      integer      not null,
            deleted          boolean      not null default false
        );");

        $this->addSql(
            'create index IF NOT EXISTS lichniy_token_account_repo_idx on lichniy_token (account_id, repo_id);'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS lichniy_token');
        $this->addSql('DROP TABLE IF EXISTS unitman_lichniy_token_event_store');
    }
}
