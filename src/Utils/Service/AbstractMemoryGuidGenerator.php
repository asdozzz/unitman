<?php

namespace App\Utils\Service;

abstract class AbstractMemoryGuidGenerator
{
    private int $currentIndex = 0;
    /**
     * @param array<non-empty-string> $guids
     * */
    public function __construct(private array $guids = [])
    {
    }

    /**
     * @return non-empty-string
     * */
    function makeGuid(): string
    {
        if (!isset($this->guids[$this->currentIndex])) {
            throw new \DomainException('Guid not found for index='.$this->currentIndex);
        }
        $guid = $this->guids[$this->currentIndex];
        $this->currentIndex++;
        return $guid;
    }
}
