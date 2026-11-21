<?php

declare(strict_types=1);

namespace App\OpenCode\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Снимок основной ветки проекта в workspace-сессии.
 *
 * `main_branch` — базовая ветка проекта на момент создания workspace; нужна
 * раннеру для локального создания ветки, отсутствующей в origin.
 */
final class Version20261121120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Workspace OpenCode: основная ветка проекта (main_branch) в снимке opencode_workspace_project';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "alter table opencode_workspace_project "
            . "add column if not exists main_branch varchar(256) not null default ''"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('alter table opencode_workspace_project drop column if exists main_branch');
    }
}
