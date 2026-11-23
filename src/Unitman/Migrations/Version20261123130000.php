<?php

declare(strict_types=1);

namespace App\Unitman\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Статус отложенного намерения.
 *
 * `ready` — намерение можно применять. `held_autoobnovlenie` — намерение
 * обновления создано, но проект временно не хочет применять обновления
 * автоматически. Такие строки видны в карточке юнита, но фоновый обход их
 * не выбирает, пока проект не включит автообновление.
 */
final class Version20261123130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Статус отложенных намерений: ready или held_autoobnovlenie';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("alter table unit_otlozhennoe_deistvie
            add column if not exists status varchar not null default 'ready'");

        $this->addSql('create index if not exists unit_otlozhennoe_deistvie_status_next_idx
            on unit_otlozhennoe_deistvie (status, next_attempt_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('drop index if exists unit_otlozhennoe_deistvie_status_next_idx');
        $this->addSql('alter table unit_otlozhennoe_deistvie drop column if exists status');
    }
}
