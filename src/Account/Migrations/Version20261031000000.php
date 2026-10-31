<?php

declare(strict_types=1);

namespace App\Account\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261031000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Таблица refresh-токенов аккаунтов';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("create table IF NOT EXISTS refresh_token
            (
                token_hash varchar(128) not null
                    constraint refresh_token_pk primary key,
                account_id varchar(128) not null,
                expires_at integer      not null,
                is_revoked boolean      not null default false
            );
        ");

        $this->addSql('create index IF NOT EXISTS refresh_token_account_id_idx on refresh_token (account_id);');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('drop table if exists refresh_token');
    }
}
