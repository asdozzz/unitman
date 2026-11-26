<?php

declare(strict_types=1);

namespace App\Account\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Подтверждение email, OAuth-identity и одноразовые коды обмена.
 *
 * `is_email_verified` по умолчанию `true`, чтобы существующие аккаунты и
 * старые pending-записи не потеряли доступ. Новые self-registration-аккаунты
 * пишут `false` явно.
 */
final class Version20261126000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Подтверждение email, social identity и OAuth exchange code';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'alter table jwt_user add column if not exists is_email_verified boolean not null default true;'
        );

        $this->addSql(
            'create table if not exists account_email_verification
            (
                account_id  varchar(128) not null
                    constraint account_email_verification_pk
                        primary key,
                email       varchar(255) not null,
                code_hash   varchar(255) not null,
                expires_at  timestamptz  not null,
                attempts    integer      not null default 0,
                sent_count  integer      not null default 1,
                last_sent_at timestamptz not null,
                consumed_at timestamptz
            );'
        );

        $this->addSql(
            'create index if not exists account_email_verification_email_idx
                on account_email_verification (email);'
        );

        $this->addSql(
            'create table if not exists account_social_identity
            (
                id               varchar(128) not null
                    constraint account_social_identity_pk
                        primary key,
                account_id       varchar(128) not null,
                provider         varchar(32)  not null,
                provider_user_id varchar(255) not null,
                email            varchar(255) not null,
                linked_at        timestamptz  not null,
                constraint account_social_identity_provider_user_uq
                    unique (provider, provider_user_id),
                constraint account_social_identity_account_provider_uq
                    unique (account_id, provider)
            );'
        );

        $this->addSql(
            'create index if not exists account_social_identity_email_idx
                on account_social_identity (email);'
        );

        $this->addSql(
            'create table if not exists oauth_exchange_code
            (
                code_hash  varchar(255) not null
                    constraint oauth_exchange_code_pk
                        primary key,
                account_id varchar(128) not null,
                expires_at timestamptz  not null,
                used_at    timestamptz,
                created_at timestamptz  not null
            );'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('drop table if exists oauth_exchange_code');
        $this->addSql('drop table if exists account_social_identity');
        $this->addSql('drop table if exists account_email_verification');
        $this->addSql('alter table jwt_user drop column if exists is_email_verified');
    }
}
