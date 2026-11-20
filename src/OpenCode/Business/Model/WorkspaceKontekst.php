<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Model;

/**
 * Контекст хода для workspace-сессии.
 *
 * Для workspace раннер не получает юнитов: рабочий каталог — сам workspace,
 * а проекты передаются отдельным списком. `readOnly` запрещает агенту
 * commit/push.
 */
final class WorkspaceKontekst
{
    /**
     * @param list<ProektWorkspace> $projects
     */
    public function __construct(
        public readonly string $workspaceId,
        public readonly bool $readOnly,
        public readonly array $projects,
    ) {
    }
}
