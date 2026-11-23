<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Command;

use App\Utils\Converter\JsonBodySerializableInterface;

/**
 * Создание юнитов для всех проектов workspace.
 */
final class SozdatUnitiWorkspace implements JsonBodySerializableInterface
{
    public function __construct(
        public readonly string $workspaceId = '',
    ) {
    }
}
