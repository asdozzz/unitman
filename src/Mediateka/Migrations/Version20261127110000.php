<?php

declare(strict_types=1);

namespace App\Mediateka\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Файлы workspace: владелец — workspace, тип файла и признак применения.
 *
 * Правило владельца расширяется: у файла может быть не более одного
 * владельца из unit_id, project_id, workspace_id. Черновик не имеет владельца.
 */
final class Version20261127110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Медиатека: файлы workspace (workspace_id, kind, applied_at)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('alter table mediateka_file add column if not exists workspace_id varchar(36) default null');
        $this->addSql('alter table mediateka_file add column if not exists kind varchar(16) default null');
        $this->addSql('alter table mediateka_file add column if not exists applied_at integer default null');

        $this->addSql(
            "update mediateka_file
             set kind = case
                 when project_id is not null then 'project'
                 when unit_id is not null then 'unit'
                 else 'draft'
             end
             where kind is null"
        );

        $this->addSql("alter table mediateka_file alter column kind set default 'draft'");
        $this->addSql('alter table mediateka_file alter column kind set not null');

        $this->addSql('alter table mediateka_file drop constraint if exists mediateka_file_owner_chk');
        $this->addSql(
            'alter table mediateka_file add constraint mediateka_file_owner_chk check (
                (unit_id is not null)::int
                + (project_id is not null)::int
                + (workspace_id is not null)::int <= 1
            )'
        );

        $this->addSql('create index if not exists mediateka_file_workspace_id_idx on mediateka_file (workspace_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('delete from mediateka_file where workspace_id is not null');
        $this->addSql('alter table mediateka_file drop constraint if exists mediateka_file_owner_chk');
        $this->addSql(
            'alter table mediateka_file add constraint mediateka_file_owner_chk check (unit_id is null or project_id is null)'
        );
        $this->addSql('drop index if exists mediateka_file_workspace_id_idx');
        $this->addSql('alter table mediateka_file drop column if exists applied_at');
        $this->addSql('alter table mediateka_file drop column if exists kind');
        $this->addSql('alter table mediateka_file drop column if exists workspace_id');
    }
}
