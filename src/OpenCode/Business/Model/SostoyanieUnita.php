<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Model;

/**
 * Состояние юнита глазами OpenCode — без внутренних классов Unitman.
 *
 * `group` — результат единой матрицы готовности (`OcenkaGotovnostiUnita`):
 * сессия может принимать сообщения только в группе `ready`.
 */
final class SostoyanieUnita
{
    public const GROUP_READY = 'ready';
    public const GROUP_BLOCKED = 'blocked';
    public const GROUP_ARCHIVED = 'archived';

    /**
     * @param bool $zanyat над юнитом идёт операция: активити Unitman или чужой ход OpenCode
     * @param string|null $prichinaZanyatosti `prozes` | `opencode_hod` — для подсказки в интерфейсе
     */
    public function __construct(
        public readonly bool $exists,
        public readonly ?string $code,
        public readonly ?string $projectId,
        public readonly ?string $name,
        public readonly ?string $branch,
        public readonly string $group,
        public readonly bool $zanyat = false,
        public readonly ?string $prichinaZanyatosti = null,
    )
    {
    }
}
