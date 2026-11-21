<?php

declare(strict_types=1);

namespace App\Runner\Business\Model\GolangRunner\Unit;

/**
 * Результат подготовки редакторской сессии юнита.
 */
final class ResultatPodgotovkiSessiiRedaktora
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
