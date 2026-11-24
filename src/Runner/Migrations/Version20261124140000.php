<?php

declare(strict_types=1);

namespace App\Runner\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Удаление legacy-ключа `proxyUrl` из JSONB-снимков `runner_state.payload`.
 *
 * `RunnerState::$proxyUrl` удалён из модели: публичный адрес раннера теперь
 * хранится в `publicAddress`. Старые payload-снимки могли содержать
 * `proxyUrl` на верхнем уровне JSON. После этой миграции Symfony Serializer
 * читает их без неиспользуемого ключа.
 *
 * Миграция идемпотентна: повторный запуск не меняет строки, в которых ключа
 * уже нет, и не затрагивает остальные поля payload.
 */
final class Version20261124140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Runner: очистка legacy-ключа proxyUrl из runner_state.payload';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "update runner_state
             set payload = payload - 'proxyUrl' - 'proxy_url'
             where jsonb_exists(payload, 'proxyUrl')
                or jsonb_exists(payload, 'proxy_url')"
        );
    }

    public function down(Schema $schema): void
    {
        // Удалённые legacy-данные не восстанавливаются: модель `RunnerState`
        // больше не знает о `proxyUrl`.
    }
}
