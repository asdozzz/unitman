<?php

declare(strict_types=1);

namespace App\Mediateka\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Архив каталога типов томов принадлежит проекту, а не юниту: поля
 * `unit_id` и `project_id` взаимоисключающие. Такие файлы переиспользуются
 * несколькими юнитами и не удаляются вместе с юнитом.
 */
final class Version20261108120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Медиатека: архив проекта (project_id), владелец файла — юнит либо проект';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('alter table mediateka_file add column if not exists project_id varchar(36) default null');
        $this->addSql('create index if not exists mediateka_file_project_id_idx on mediateka_file (project_id)');
        $this->addSql('alter table mediateka_file add constraint mediateka_file_owner_chk check (unit_id is null or project_id is null)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('alter table mediateka_file drop constraint if exists mediateka_file_owner_chk');
        $this->addSql('drop index if exists mediateka_file_project_id_idx');
        $this->addSql('alter table mediateka_file drop column if exists project_id');
    }
}
