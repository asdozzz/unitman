<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Command;

use App\Utils\Converter\JsonBodySerializableInterface;

/**
 * Полное удаление workspace-сессии.
 *
 * Контейнеры и тома удаляет Runner, а записи БД — фоновая задача после
 * успешного завершения операции удаления.
 */
readonly class UdalitWorkspace implements JsonBodySerializableInterface
{
    public function __construct(
        public string $workspaceId = '',
    ) {
    }
}
