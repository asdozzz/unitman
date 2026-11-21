<?php

declare(strict_types=1);

namespace App\Unitman\Business\Model\Unit\Event;

/**
 * Пользователь откреплён от юнита как редактор.
 */
final class RedaktorOtkreplenOtUnitu
{
    public function __construct(
        public readonly string $unitId,
        public readonly string $userId,
    )
    {
    }
}
