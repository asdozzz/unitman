<?php

namespace App\Runner\Business\Command;

final class NachatPodgotovkuUnita
{
    public function __construct(
        public readonly string $ProjectId,
        public readonly string $Id,
        public readonly string $Name,
        public readonly string $StorageUrl,
        public readonly array $Commands,
        public readonly array $Variables,
    )
    {
    }
}
