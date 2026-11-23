<?php

declare(strict_types=1);

namespace App\OpenCode\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Юниты, созданные из workspace.
 *
 * Снимок нужен, чтобы карточка workspace показывала созданные юниты и вела
 * на страницу юнитов с фильтром `unitIds` без дополнительных запросов.
 */
final class Version20261123120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Workspace OpenCode: таблица созданных из workspace юнитов';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'create table if not exists opencode_workspace_unit ('
            . 'workspace_id varchar(64) not null, '
            . 'unit_id varchar(64) not null, '
            . 'project_id varchar(64) not null, '
            . 'unit_name varchar(128) not null, '
            . 'branch varchar(256) not null default \'\', '
            . 'created_at integer not null, '
            . 'primary key (workspace_id, unit_id)'
            . ')'
        );

        $this->addSql(
            'create index if not exists opencode_workspace_unit_workspace_idx '
            . 'on opencode_workspace_unit (workspace_id)'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('drop index if exists opencode_workspace_unit_workspace_idx');
        $this->addSql('drop table if exists opencode_workspace_unit');
    }
}
