<?php

declare(strict_types=1);

namespace App\Unitman\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Занятость юнита и отложенные действия из Git-вебхука.
 *
 * 1. `unit_zanyatost` — реестр «юнит кем-то занят». Строка живёт, пока
 *    владелец не освободит юнит или пока не истечёт TTL. Владелец — непрозрачный
 *    для Unitman токен (`opencode:<turnId>`), поэтому модуль Unitman не знает
 *    о существовании OpenCode. Уникальный первичный ключ по `unit_id` — это
 *    и есть точка сериализации: захват делает
 *    `INSERT ... ON CONFLICT (unit_id) DO UPDATE ... WHERE istekaet_v < now()`,
 *    и `rowCount() = 0` означает, что юнит уже держит кто-то другой.
 *
 *    Индекс по `istekaet_v` нужен фоновому обходу, который вычищает забытые
 *    захваты: без него каждая чистка читала бы таблицу целиком.
 *
 * 2. `unit_otlozhennoe_deistvie` — durable-намерение применить событие вебхука
 *    позже. Пока над юнитом идёт активити (или ход OpenCode), запускать
 *    обновление/удаление нельзя, но и терять событие нельзя: job вебхука
 *    ретраится лишь трижды, после чего событие исчезает. Поэтому намерение
 *    пишется в таблицу и применяется при первом освобождении юнита.
 *
 *    `unique (unit_id, tip_sobitiya)` — повторный вебхук по той же ветке
 *    обновляет существующее намерение, а не плодит дубликаты. Приоритет
 *    `VETKA_UDALENA` над `KOD_OBNOVLEN` обеспечивается кодом: при постановке
 *    удаления строки обновления по этому юниту удаляются.
 *
 *    `payload` — jsonb с данными события (ветка, unixtime), чтобы применить
 *    намерение без повторного чтения вебхука.
 */
final class Version20261115120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Реестр занятости юнитов и отложенные действия из вебхука';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('create table if not exists unit_zanyatost
            (
                unit_id varchar primary key,
                vladelec varchar not null,
                tip varchar not null,
                zahvacheno_v timestamptz not null,
                istekaet_v timestamptz not null
            )');

        $this->addSql('create index if not exists unit_zanyatost_istekaet_v_idx
            on unit_zanyatost (istekaet_v)');

        $this->addSql('create table if not exists unit_otlozhennoe_deistvie
            (
                id bigserial primary key,
                unit_id varchar not null,
                project_id varchar not null,
                tip_sobitiya varchar not null,
                payload jsonb not null,
                created_at timestamptz not null,
                attempts int not null default 0,
                next_attempt_at timestamptz not null,
                last_error text null
            )');

        $this->addSql('create unique index if not exists unit_otlozhennoe_deistvie_unit_tip_uniq
            on unit_otlozhennoe_deistvie (unit_id, tip_sobitiya)');

        $this->addSql('create index if not exists unit_otlozhennoe_deistvie_next_attempt_at_idx
            on unit_otlozhennoe_deistvie (next_attempt_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('drop index if exists unit_otlozhennoe_deistvie_next_attempt_at_idx');
        $this->addSql('drop index if exists unit_otlozhennoe_deistvie_unit_tip_uniq');
        $this->addSql('drop table if exists unit_otlozhennoe_deistvie');

        $this->addSql('drop index if exists unit_zanyatost_istekaet_v_idx');
        $this->addSql('drop table if exists unit_zanyatost');
    }
}
