<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Model;

/**
 * Нейтральные для OpenCode параметры создания юнита: сессия может попросить
 * создать юнит от имени владельца, не зная команд Unitman.
 */
final class ParametriSozdaniyaUnita
{
    /**
     * @param list<array{id: string, value: string, type: string, targetUnitId: ?string}> $peremenie
     * @param list<array{dir: string, name: string, fileId: ?string}> $volumes
     */
    public function __construct(
        public readonly string $projectId,
        public readonly string $unitName,
        public readonly string $branch,
        public readonly int $memoryLimit = 0,
        public readonly array $peremenie = [],
        public readonly array $volumes = [],
    )
    {
    }
}
