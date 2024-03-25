<?php

namespace App\Utils\EventSauce\Model;

final class StreamName
{
    public function __construct(public readonly string $aggregateType, public readonly array $eventTypes = [])
    {
    }

}
