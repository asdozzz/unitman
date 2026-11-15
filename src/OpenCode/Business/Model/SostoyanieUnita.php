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

    public function __construct(
        public readonly bool $exists,
        public readonly ?string $code,
        public readonly ?string $projectId,
        public readonly ?string $name,
        public readonly ?string $branch,
        public readonly string $group,
    )
    {
    }
}
