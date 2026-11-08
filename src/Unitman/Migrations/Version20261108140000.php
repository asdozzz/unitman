<?php

declare(strict_types=1);

namespace App\Unitman\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Связи переменных конфига юнита с юнитами, выбранными через хелпер.
 *
 * `svyazi_unitov` хранит по одной строке на переменную конфига
 * (`primary key (unit_id, variable_id)`), поэтому замена набора связей
 * идемпотентна. `project_id` цели денормализован, чтобы фильтр списка
 * юнитов проверял доступность проекта без джойна `spisok_unitov`.
 */
final class Version20261108140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Связи переменных юнита с юнитами, выбранными через хелпер';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('create table if not exists svyazi_unitov
            (
                unit_id varchar(128) not null,
                variable_id varchar(128) not null,
                target_unit_id varchar(128) not null,
                project_id varchar(128) not null,
                created_at integer not null,
                primary key (unit_id, variable_id)
            )');

        $this->addSql('create index if not exists svyazi_unitov_target_idx
            on svyazi_unitov (target_unit_id)');

        $this->addSql('create index if not exists svyazi_unitov_project_idx
            on svyazi_unitov (project_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('drop table if exists svyazi_unitov');
    }
}
