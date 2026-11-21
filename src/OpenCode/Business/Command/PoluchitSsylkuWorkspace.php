<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Command;

use App\Utils\Converter\JsonBodySerializableInterface;

/**
 * Ссылка на произвольный порт workspace-контейнера.
 *
 * Аналог юнитного `PoluchitSsylkuPoPomoschniku`: `suffix` дописывается к
 * базовой ссылке (ведущий `/` нормализуется), `type` задаёт протокол
 * (`http` по умолчанию, для `ws`/`wss` схема выбирается по схеме proxy).
 */
final class PoluchitSsylkuWorkspace implements JsonBodySerializableInterface
{
    public function __construct(
        public string $workspaceId = '',
        public int $port = 0,
        public ?string $suffix = null,
        public ?string $type = null,
    ) {
    }
}
