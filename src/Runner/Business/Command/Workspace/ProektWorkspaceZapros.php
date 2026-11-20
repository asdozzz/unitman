<?php

declare(strict_types=1);

namespace App\Runner\Business\Command\Workspace;

/**
 * Публичный проект в составе запроса на запуск workspace.
 *
 * Не содержит `StorageUrl`: токенизированный URL добавляет сам `RunnerApi`
 * через порт `UmeetPoluchatUrlKlonaProekta`, поэтому OpenCode никогда не видит
 * токен.
 */
final class ProektWorkspaceZapros
{
    public function __construct(
        public readonly string $ProjectId,
        public readonly string $ProjectName,
        public readonly string $ProjectCode,
        public readonly bool $CanPush,
    ) {
    }
}
