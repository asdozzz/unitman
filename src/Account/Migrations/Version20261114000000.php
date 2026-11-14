<?php

declare(strict_types=1);

namespace App\Account\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261114000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Реквизиты подключения к OpenCode (API-ключ хранится в зашифрованном виде)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("create table IF NOT EXISTS opencode_credential
            (
                id             varchar(64)  not null
                    constraint opencode_credential_pk primary key,
                account_id     varchar(128) not null,
                name           varchar(128) not null,
                url            varchar(512) not null,
                model          varchar(128) not null,
                api_key_cipher text         not null,
                api_key_hint   varchar(32)  not null,
                cipher_version smallint     not null default 1,
                created_at     integer      not null,
                updated_at     integer      not null
            );
        ");

        $this->addSql('create unique index IF NOT EXISTS opencode_credential_account_name_uq on opencode_credential (account_id, name);');
        $this->addSql('create index IF NOT EXISTS opencode_credential_account_id_idx on opencode_credential (account_id);');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('drop table if exists opencode_credential');
    }
}
