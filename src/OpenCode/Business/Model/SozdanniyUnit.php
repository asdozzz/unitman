<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Model;

/**
 * Нейтральный для OpenCode результат создания юнита из workspace.
 */
final class SozdanniyUnit
{
    public function __construct(
        public readonly string $unitId,
        public readonly string $projectId,
        public readonly string $unitName,
        public readonly string $branch,
    ) {
    }
}
