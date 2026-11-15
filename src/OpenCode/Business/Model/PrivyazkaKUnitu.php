<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Model;

/**
 * Привязка сессии к одному юниту: идентификатор юнита и снимок его проекта.
 *
 * Снимок проекта обязателен — без него раннер не определит директорию юнита.
 * Первая привязка в списке задаёт рабочий каталог хода (cwd), остальные
 * подключаются к OpenCode как дополнительные корни workspace (multi-root).
 */
final class PrivyazkaKUnitu
{
    private function __construct(
        public readonly string $unitId,
        public readonly string $unitProjectId,
    )
    {
    }

    /**
     * @throws \DomainException если юнит не указан или у юнита нет проекта
     */
    public static function izZnacheniy(?string $unitId, ?string $unitProjectId): self
    {
        if ($unitId === null || trim($unitId) === '') {
            throw new \DomainException('opencode.session.unit_required');
        }

        return new self(trim($unitId), self::normalizeProjectId($unitProjectId));
    }

    /**
     * Мягкий вариант для восстановления из хранилища: пустой юнит — это отсутствие привязки.
     */
    public static function izZnacheniyIliNull(?string $unitId, ?string $unitProjectId): ?self
    {
        if ($unitId === null || trim($unitId) === '') {
            return null;
        }

        return self::izZnacheniy($unitId, $unitProjectId);
    }

    public function prinadlezhitKUnitu(string $unitId): bool
    {
        return $this->unitId === $unitId;
    }

    public function prinadlezhitKProektu(string $unitProjectId): bool
    {
        return $this->unitProjectId === $unitProjectId;
    }

    private static function normalizeProjectId(?string $unitProjectId): string
    {
        $normalized = $unitProjectId === null ? '' : trim($unitProjectId);

        if ($normalized === '') {
            throw new \DomainException('opencode.session.unit_project_required');
        }

        return $normalized;
    }
}
