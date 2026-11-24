<?php

declare(strict_types=1);

namespace App\Unitman\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Очистка legacy-ключей proxy-модели из JSONB-проекций event-sourced проекта.
 *
 * 1. `unitman_event_store.payload` — сериализованное сообщение EventSauce:
 *    `{"headers": {...}, "payload": {...поля события...}}`. ObjectHydrator
 *    пишет свойства события в snake_case, поэтому в старых событиях
 *    `ProjectWasAdded` / `ProjectDataWasChanged` внутри `payload->'payload'`
 *    остались `proxy_host` / `new_proxy_host` (а также camelCase-варианты
 *    из более ранних сериализаторов). Классы событий эти свойства больше не
 *    объявляют, поэтому ключи удаляются до десериализации.
 *
 * 2. `project_list.payload` — JSONB read-модель проекта, где `proxyHost`
 *    лежал на верхнем уровне. Таблица создаётся лениво
 *    (`SqlProjectListRepository::init()`), поэтому UPDATE выполняется только
 *    при её наличии.
 *
 * Обе операции идемпотентны: WHERE проверяет наличие ключа, `#-`/`-` не
 * трогают остальные поля. Исторические migration-файлы не изменяются.
 */
final class Version20261124150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Unitman: очистка proxyHost/newProxyHost из event store и project_list';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            update unitman_event_store
            set payload = payload
                #- '{payload,proxy_host}' #- '{payload,new_proxy_host}'
                #- '{payload,proxyHost}'  #- '{payload,newProxyHost}'
            where jsonb_exists(payload->'payload', 'proxy_host')
               or jsonb_exists(payload->'payload', 'new_proxy_host')
               or jsonb_exists(payload->'payload', 'proxyHost')
               or jsonb_exists(payload->'payload', 'newProxyHost')
            SQL);

        $this->addSql(<<<'SQL'
            do $$
            begin
                if to_regclass('project_list') is not null then
                    update project_list
                    set payload = payload - 'proxyHost' - 'proxy_host'
                    where jsonb_exists(payload, 'proxyHost')
                       or jsonb_exists(payload, 'proxy_host');
                end if;
            end
            $$;
            SQL);
    }

    public function down(Schema $schema): void
    {
        // Удалённые legacy-ключи не восстанавливаются: модель Project больше
        // не содержит `proxyHost`.
    }
}
