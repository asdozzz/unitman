<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Workspace\Model;

/**
 * Фильтр списка workspace-сессий. Тип сессии не нужен: это отдельный агрегат.
 */
final class WorkspaceFilter
{
    public function __construct(
        public readonly bool $onlyActive = false,
        public readonly ?string $search = null,
    ) {
    }

    public function estPoisk(): bool
    {
        return $this->search !== null && trim($this->search) !== '';
    }
}
