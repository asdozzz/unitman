<?php

declare(strict_types=1);

namespace App\Unitman\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Каталог типов `unitman_volumes` на уровне проекта.
 *
 * `unitman_volume_type` — справочник типов (`code` совпадает с `dir` тома),
 * `unitman_volume_type_file` — связи типа с архивами медиатеки. Связи хранят
 * `project_id` денормализованно, чтобы проверки принадлежности выполнялись
 * одним индексным запросом.
 */
final class Version20261108130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Каталог типов томов проекта и связи с архивами медиатеки';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('create table if not exists unitman_volume_type
            (
                id varchar(36) not null primary key,
                project_id varchar(36) not null,
                code varchar(100) not null,
                name varchar(255) not null,
                created_at integer not null
            )');

        $this->addSql('create unique index if not exists unitman_volume_type_project_code_uniq
            on unitman_volume_type (project_id, code)');

        $this->addSql('create table if not exists unitman_volume_type_file
            (
                type_id varchar(36) not null,
                project_id varchar(36) not null,
                file_id varchar(36) not null,
                created_at integer not null,
                primary key (type_id, file_id)
            )');

        $this->addSql('create index if not exists unitman_volume_type_file_project_file_idx
            on unitman_volume_type_file (project_id, file_id)');

        $this->addSql('alter table unitman_volume_type_file
            add constraint unitman_volume_type_file_type_fk
            foreign key (type_id) references unitman_volume_type (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('alter table unitman_volume_type_file drop constraint if exists unitman_volume_type_file_type_fk');
        $this->addSql('drop table if exists unitman_volume_type_file');
        $this->addSql('drop table if exists unitman_volume_type');
    }
}
