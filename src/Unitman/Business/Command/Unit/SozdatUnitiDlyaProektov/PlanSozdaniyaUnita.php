<?php

declare(strict_types=1);

namespace App\Unitman\Business\Command\Unit\SozdatUnitiDlyaProektov;

use App\Unitman\Business\Model\Unit\ConfigUnita;

/**
 * Внутренний план группового создания: всё, что нужно для одного проекта
 * до записи события `UnitSozdan`.
 */
final class PlanSozdaniyaUnita
{
    public function __construct(
        public readonly string $projectId,
        public readonly string $projectName,
        public readonly string $projectCode,
        public readonly string $branch,
        public readonly string $unitId,
        public readonly string $prozesId,
        public readonly int $memoryLimit,
        public readonly ConfigUnita $config,
        public readonly ?string $runnerId = null,
    ) {
    }
}
