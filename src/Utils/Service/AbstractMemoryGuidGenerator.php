<?php

namespace App\Utils\Service;

abstract class AbstractMemoryGuidGenerator
{
    private int $currentIndex = 0;
    /**
     * @param array<string> $guids
     * */
    public function __construct(private array $guids = [])
    {
    }

    function makeGuid(): string
    {
        $guid = $this->guids[$this->currentIndex];
        $this->currentIndex++;
        return $guid;
    }
}
