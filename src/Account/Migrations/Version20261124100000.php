<?php

declare(strict_types=1);

namespace App\Account\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Провайдер и несколько моделей у реквизитов OpenCode.
 *
 * Колонка `opencode_credential.model` остаётся ради обратной совместимости и
 * всегда равна дефолтной модели. Набор моделей живёт в
 * `opencode_credential_model`, дефолт помечается `is_default = true`.
 * Существующие записи бэкфиллятся: одна модель из `opencode_credential.model`
 * становится дефолтной.
 */
final class Version20261124100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Провайдер и набор моделей реквизитов OpenCode (opencode_credential_model)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("alter table opencode_credential
            add column if not exists provider varchar(64) not null default 'custom';");

        $this->addSql("create table if not exists opencode_credential_model
            (
                credential_id varchar(64)  not null,
                model         varchar(128) not null,
                is_default    boolean      not null default false,
                created_at    integer      not null,
                primary key (credential_id, model)
            );");

        $this->addSql('create unique index if not exists opencode_credential_model_default_uq
            on opencode_credential_model (credential_id) where is_default;');

        $this->addSql('create index if not exists opencode_credential_model_credential_idx
            on opencode_credential_model (credential_id);');

        $this->addSql('insert into opencode_credential_model (credential_id, model, is_default, created_at)
            select c.id, c.model, true, c.created_at
            from opencode_credential c
            where not exists (
                select 1 from opencode_credential_model m where m.credential_id = c.id
            );');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('drop table if exists opencode_credential_model');
        $this->addSql('alter table opencode_credential drop column if exists provider');
    }
}
