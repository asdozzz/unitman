<?php

declare(strict_types=1);

namespace App\OpenCode\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Фактическая ветка проекта в снимке workspace.
 *
 * `branch` — ветка, с которой проект клонируется в workspace: переопределение
 * из `projectBranches` или общая ветка workspace. Пустое значение у старых
 * строк означает fallback на `opencode_workspace.branch`.
 */
final class Version20261121130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Workspace OpenCode: ветка проекта (branch) в снимке opencode_workspace_project';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "alter table opencode_workspace_project "
            . "add column if not exists branch varchar(256) not null default ''"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('alter table opencode_workspace_project drop column if exists branch');
    }
}
