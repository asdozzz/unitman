<?php

namespace App\Unitman\Business\Model\Unit;

final class ContainerSettings
{
    public readonly int $memoryLimit;
    public function __construct(int $memoryLimit)
    {
        self::validateMemory($memoryLimit);
        $this->memoryLimit = $memoryLimit;
    }

    static function validateMemory(int $memoryLimit): void
    {
        if ($memoryLimit < 300) {
            throw new \DomainException('unit.memory_limit_min_300');
        }
    }
}
