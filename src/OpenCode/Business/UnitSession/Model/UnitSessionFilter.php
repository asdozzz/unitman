<?php

declare(strict_types=1);

namespace App\OpenCode\Business\UnitSession\Model;

/**
 * Фильтр списка unit-сессий. Тип сессии больше не нужен: других типов здесь нет.
 */
final class UnitSessionFilter
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
