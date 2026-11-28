<?php

declare(strict_types=1);

namespace App\Skill\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Пользовательские навыки OpenCode: глобальные и проектные.
 *
 * Таблицы не имеют внешних ключей на проекты: проекты ведутся event-sourcing
 * (проекции `project_list`) и удаляются асинхронно, поэтому FK на них
 * недопустим. Целостность привязок обеспечивает бизнес-слой.
 *
 * Уникальность slug глобальных навыков НЕ выражена частичным уникальным
 * индексом: проект поддерживает Postgres, MariaDB и MySQL, а частичные
 * индексы (`... where archived_at is null`) переносимы не везде. Поэтому
 * используется обычный индекс для поиска, а уникальность среди неархивных
 * глобальных навыков проверяется в коде (см. SqlNavykRepository).
 */
final class Version20261128120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Навыки OpenCode: глобальные/проектные, привязка к проектам и история версий';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("create table IF NOT EXISTS opencode_skill
            (
                id           varchar(64)  not null
                    constraint opencode_skill_pk primary key,
                slug         varchar(64)  not null,
                name         varchar(128) not null,
                description  varchar(512) not null default '',
                content      text         not null,
                content_hash varchar(64)  not null,
                version      integer      not null default 1,
                is_global    boolean      not null default false,
                created_by   varchar(128) not null,
                updated_by   varchar(128) not null,
                created_at   integer      not null,
                updated_at   integer      not null,
                archived_at  integer
            );
        ");

        // Обычный индекс: используется для поиска глобального навыка по slug.
        // Уникальность среди неархивных глобальных навыков проверяется в коде.
        $this->addSql('create index IF NOT EXISTS opencode_skill_global_slug_idx on opencode_skill (is_global, slug);');
        $this->addSql('create index IF NOT EXISTS opencode_skill_archived_idx on opencode_skill (archived_at);');

        $this->addSql("create table IF NOT EXISTS opencode_skill_project
            (
                skill_id   varchar(64)  not null,
                project_id varchar(64)  not null,
                added_by   varchar(128) not null,
                added_at   integer      not null,
                constraint opencode_skill_project_pk primary key (skill_id, project_id)
            );
        ");

        // PK покрывает префикс skill_id; отдельный индекс нужен для выборки
        // навыков по проекту.
        $this->addSql('create index IF NOT EXISTS opencode_skill_project_project_idx on opencode_skill_project (project_id);');

        $this->addSql("create table IF NOT EXISTS opencode_skill_version
            (
                skill_id     varchar(64)  not null,
                version      integer      not null,
                name         varchar(128) not null,
                description  varchar(512) not null default '',
                content      text         not null,
                content_hash varchar(64)  not null,
                created_by   varchar(128) not null,
                created_at   integer      not null,
                constraint opencode_skill_version_pk primary key (skill_id, version)
            );
        ");
    }

    public function down(Schema $schema): void
    {
        // Удаляем в обратном порядке; индексы уходят вместе с таблицами.
        $this->addSql('drop table if exists opencode_skill_version');
        $this->addSql('drop table if exists opencode_skill_project');
        $this->addSql('drop table if exists opencode_skill');
    }
}
