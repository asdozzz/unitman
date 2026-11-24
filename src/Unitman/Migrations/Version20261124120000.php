<?php

declare(strict_types=1);

namespace App\Unitman\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Жёсткая привязка юнита к раннеру (multi-runner).
 *
 * Юнит живёт в event store, отдельной таблицы `unit` нет, поэтому физический
 * FK на `unit_id` невозможен — целостность обеспечивает прикладной слой.
 * Подробности контракта: plans/runner-multi-runner.md.
 */
final class Version20261124120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Unitman: таблица привязки юнитов к раннерам (unit_runner)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'create table if not exists unit_runner ('
            . 'unit_id varchar(64) not null primary key, '
            . 'runner_id varchar(128) not null, '
            . 'created_at integer not null'
            . ')'
        );

        $this->addSql(
            'create index if not exists unit_runner_runner_idx '
            . 'on unit_runner (runner_id)'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('drop index if exists unit_runner_runner_idx');
        $this->addSql('drop table if exists unit_runner');
    }
}
