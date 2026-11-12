<?php

declare(strict_types=1);

namespace App\Unitman\Tests\Support;

use App\Unitman\Business\Port\CanGenerateGuid;

/**
 * Последовательный генератор идентификаторов: `type-1`, `type-2`, ...
 */
final class SequenceGuidGenerator implements CanGenerateGuid
{
    private int $counter = 0;

    public function __construct(private readonly string $prefix = 'type-')
    {
    }

    public function makeGuid(): string
    {
        ++$this->counter;

        return $this->prefix . $this->counter;
    }
}
