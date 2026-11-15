<?php

declare(strict_types=1);

namespace App\OpenCode\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Привязка сессии OpenCode к юниту и архивация сессий.
 *
 * unit_id         — юнит, в директории которого работают все ходы сессии (null — свободная сессия);
 * unit_project_id — снимок проекта юнита на момент привязки, идёт в активити хода;
 * archived_at     — время архивации; null — сессия активна;
 * archive_reason  — причина архивации: unit_deleted (автоматически) или manual.
 */
final class Version20261120120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Сессия OpenCode: привязка к юниту (unit_id, unit_project_id) и архивация (archived_at, archive_reason)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('alter table opencode_session add column if not exists unit_id varchar(64) default null');
        $this->addSql('alter table opencode_session add column if not exists unit_project_id varchar(64) default null');
        $this->addSql('alter table opencode_session add column if not exists archived_at integer default null');
        $this->addSql('alter table opencode_session add column if not exists archive_reason varchar(64) default null');

        $this->addSql('create index if not exists opencode_session_unit_idx on opencode_session (unit_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('drop index if exists opencode_session_unit_idx');

        $this->addSql('alter table opencode_session drop column if exists archive_reason');
        $this->addSql('alter table opencode_session drop column if exists archived_at');
        $this->addSql('alter table opencode_session drop column if exists unit_project_id');
        $this->addSql('alter table opencode_session drop column if exists unit_id');
    }
}
