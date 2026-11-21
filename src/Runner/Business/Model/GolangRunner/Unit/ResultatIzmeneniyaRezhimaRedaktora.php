<?php

declare(strict_types=1);

namespace App\Runner\Business\Model\GolangRunner\Unit;

/**
 * Результат пересоздания IDE-сайдкара с новым режимом редактора.
 */
final class ResultatIzmeneniyaRezhimaRedaktora
{
    /**
     * @param array<Step> $Steps
     */
    public function __construct(
        public readonly int $Success,
        public array $Steps,
        public readonly string $ResponseId = '',
    ) {
    }
}
