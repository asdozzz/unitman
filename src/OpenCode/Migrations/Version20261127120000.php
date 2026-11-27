<?php

declare(strict_types=1);

namespace App\OpenCode\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * In-flight изменение workspace: добавление проектов и файлов.
 *
 * Пока операция выполняется, чат workspace заблокирован (`updating=true`),
 * а payload хранит только то, что нужно применить при успехе.
 */
final class Version20261127120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'OpenCode: pending-обновления workspace (opencode_workspace_update)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'create table if not exists opencode_workspace_update (
                session_id varchar(64) not null primary key,
                workflow_id varchar(64) not null,
                payload jsonb not null,
                created_at integer not null
            )'
        );

        $this->addSql(
            'alter table opencode_workspace_update
             add constraint opencode_workspace_update_session_fk
             foreign key (session_id) references opencode_workspace (session_id) on delete cascade'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('drop table if exists opencode_workspace_update');
    }
}
