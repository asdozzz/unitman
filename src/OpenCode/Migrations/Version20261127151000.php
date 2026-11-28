<?php

declare(strict_types=1);

namespace App\OpenCode\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Expand-миграция M1b: внешние ключи «ребёнок → сессия» нового workspace-агрегата.
 *
 * Ключи добавляются как `not valid`, чтобы не держать тяжёлую проверку на
 * больших таблицах в момент добавления. После добавления выполняется
 * `validate`, но только если в целевой таблице нет сирот: на dev/staging там
 * могли остаться строки от экспериментов, и валидация не должна ломать
 * миграцию. Сирот назовёт `app:opencode:workspace-migration:check`.
 *
 * FK на проект/ход/уведомление — страховка от рассинхронизации, порядок
 * удаления в репозитории уже «дети → родитель».
 */
final class Version20261127151000 extends AbstractMigration
{
    private const CHILDREN = [
        'opencode_workspace_session_project' => 'opencode_workspace_session_project_workspace_fk',
        'opencode_workspace_turn' => 'opencode_workspace_turn_workspace_fk',
        'opencode_workspace_notification' => 'opencode_workspace_notification_workspace_fk',
    ];

    public function getDescription(): string
    {
        return 'OpenCode: FK дочерних таблиц нового workspace-агрегата (not valid + validate при отсутствии сирот)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "do \$\$ begin
                if not exists (
                    select 1 from pg_constraint where conname = 'opencode_workspace_session_project_workspace_fk'
                ) then
                    alter table opencode_workspace_session_project
                        add constraint opencode_workspace_session_project_workspace_fk
                        foreign key (workspace_id) references opencode_workspace_session (id)
                        on delete cascade not valid;
                end if;
             end \$\$"
        );

        $this->addSql(
            "do \$\$ begin
                if not exists (
                    select 1 from pg_constraint where conname = 'opencode_workspace_turn_workspace_fk'
                ) then
                    alter table opencode_workspace_turn
                        add constraint opencode_workspace_turn_workspace_fk
                        foreign key (workspace_id) references opencode_workspace_session (id)
                        on delete cascade not valid;
                end if;
             end \$\$"
        );

        $this->addSql(
            "do \$\$ begin
                if not exists (
                    select 1 from pg_constraint where conname = 'opencode_workspace_notification_workspace_fk'
                ) then
                    alter table opencode_workspace_notification
                        add constraint opencode_workspace_notification_workspace_fk
                        foreign key (workspace_id) references opencode_workspace_session (id)
                        on delete cascade not valid;
                end if;
             end \$\$"
        );

        foreach (self::CHILDREN as $table => $constraint) {
            $this->addSql(
                "do \$\$ begin
                    if not exists (
                        select 1 from $table c
                        left join opencode_workspace_session s on s.id = c.workspace_id
                        where s.id is null
                    ) then
                        alter table $table validate constraint $constraint;
                    end if;
                 end \$\$"
            );
        }
    }

    public function down(Schema $schema): void
    {
        foreach (self::CHILDREN as $table => $constraint) {
            $this->addSql("alter table $table drop constraint if exists $constraint");
        }
    }
}
