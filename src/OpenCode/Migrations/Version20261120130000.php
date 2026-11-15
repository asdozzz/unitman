<?php

declare(strict_types=1);

namespace App\OpenCode\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Несколько юнитов на одну сессию OpenCode.
 *
 * opencode_session_unit — упорядоченный список юнитов сессии:
 *   position_index = 0 — первый юнит, его директория становится рабочим каталогом (cwd) хода;
 *   остальные юниты раннер подключает к OpenCode как дополнительные корни workspace (multi-root).
 *
 * Легаси-колонки opencode_session.unit_id / unit_project_id остаются снимком первого юнита:
 * они обеспечивают обратную совместимость контракта с раннером (ProjectId/UnitId) и старых читателей.
 */
final class Version20261120130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Сессия OpenCode: список юнитов (opencode_session_unit) и бэкфилл из opencode_session.unit_id';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'create table if not exists opencode_session_unit ('
            . 'session_id varchar(64) not null, '
            . 'unit_id varchar(64) not null, '
            . 'unit_project_id varchar(64) not null, '
            . 'position_index integer not null default 0, '
            . 'created_at integer not null, '
            . 'primary key (session_id, unit_id)'
            . ')'
        );

        $this->addSql('create index if not exists opencode_session_unit_unit_idx on opencode_session_unit (unit_id)');

        $this->addSql('create index if not exists opencode_session_unit_session_idx on opencode_session_unit (session_id)');

        $this->addSql(
            'insert into opencode_session_unit (session_id, unit_id, unit_project_id, position_index, created_at) '
            . 'select id, unit_id, unit_project_id, 0, created_at from opencode_session '
            . 'where unit_id is not null and unit_project_id is not null '
            . 'on conflict do nothing'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('drop table if exists opencode_session_unit');
    }
}
