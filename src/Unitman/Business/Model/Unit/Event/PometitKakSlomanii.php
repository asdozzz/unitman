<?php

namespace App\Unitman\Business\Model\Unit\Event;

final class PometitKakSlomanii
{
    public function __construct(
        public readonly string $unitId,
        public readonly array $stateAsArray,
        public readonly ?array $prozess,
    )
    {}
}
