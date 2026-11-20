<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Model;

/**
 * Фильтр списка сессий пользователя.
 *
 * onlyActive = false (по умолчанию) — показываем все сессии, включая архивные;
 * search — подстрока названия сессии, ищется через LIKE;
 * type — тип сессии (unit|workspace), null — все типы.
 */
final class SessiyaOpenCodeFilter
{
    private const SEARCH_MAX_LENGTH = 256;

    public function __construct(
        private bool $onlyActive = false,
        private ?string $search = null,
        private ?TipSessiiOpenCode $type = null,
    ) {
        $this->search = self::normalizeSearch($search);
    }

    public static function vsya(): self
    {
        return new self();
    }

    public static function tolkoAktivnie(): self
    {
        return new self(true);
    }

    public function isOnlyActive(): bool
    {
        return $this->onlyActive;
    }

    public function getSearch(): ?string
    {
        return $this->search;
    }

    public function getType(): ?TipSessiiOpenCode
    {
        return $this->type;
    }

    public function estPoisk(): bool
    {
        return $this->search !== null;
    }

    private static function normalizeSearch(?string $search): ?string
    {
        if ($search === null) {
            return null;
        }

        $normalized = mb_substr(trim($search), 0, self::SEARCH_MAX_LENGTH);

        return $normalized === '' ? null : $normalized;
    }
}
