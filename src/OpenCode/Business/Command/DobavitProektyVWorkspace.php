<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Command;

use App\Utils\Converter\JsonBodySerializableInterface;

/**
 * Добавление проектов в существующий workspace.
 *
 * Локальные ветки создаются клиентом в хранилище до вызова этого запроса;
 * бэкенд только проверяет доступ и запускает обновление контейнера.
 */
readonly class DobavitProektyVWorkspace implements JsonBodySerializableInterface
{
    /**
     * @param list<array{projectId?: mixed, branch?: mixed}> $projects
     */
    public function __construct(
        public string $workspaceId = '',
        public array $projects = [],
    ) {
    }
}
