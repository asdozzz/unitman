<?php

declare(strict_types=1);

namespace App\Unitman\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Индексы, без которых очередь юнитов и чтение event store деградируют.
 *
 * 1. `ochered_unitov (unit_id)` — уникальный. Нужен коду: проекция кладёт
 *    юнита в очередь через `INSERT ... ON CONFLICT (unit_id) DO UPDATE`,
 *    то есть upsert требует уникального индекса как арбитра конфликта.
 *    Заодно он исключает дубли строк по одному юниту, из-за которых
 *    воркеры обрабатывали один и тот же юнит несколько раз.
 *    Перед созданием индекса дубли удаляются: остаётся строка с наибольшим
 *    id (последняя), так как порядок опроса задаётся last_update.
 *
 * 2. `unitman_event_store (payload->'headers'->>'__aggregate_root_type', id)` —
 *    выражение, ровно повторяющее условие из
 *    `DoctrineStreamRepository::getStream()`: фильтр по типу агрегата плюс
 *    `ORDER BY id`. Раньше по этим колонкам индексов не было вовсе, каждый
 *    проход проекции читал всю таблицу событий и сортировал её.
 */
final class Version20261113120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Уникальный индекс очереди юнитов и индекс event store по типу агрегата';
    }

    public function up(Schema $schema): void
    {
        // Таблицу создаёт OcheredUnitovRepository::init(); на чистой базе
        // её может ещё не быть.
        $this->addSql('create table if not exists ochered_unitov
            (
                id serial primary key,
                unit_id varchar not null,
                last_update timestamp not null
            )');

        $this->addSql('delete from ochered_unitov a
            using ochered_unitov b
            where a.id < b.id
              and a.unit_id = b.unit_id');

        $this->addSql('create unique index if not exists ochered_unitov_unit_id_uniq
            on ochered_unitov (unit_id)');

        $this->addSql("create index if not exists unitman_event_store_aggregate_type_id_idx
            on unitman_event_store ((payload->'headers'->>'__aggregate_root_type'), id)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('drop index if exists unitman_event_store_aggregate_type_id_idx');
        $this->addSql('drop index if exists ochered_unitov_unit_id_uniq');
    }
}
