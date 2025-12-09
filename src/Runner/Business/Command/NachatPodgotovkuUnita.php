<?php

namespace App\Runner\Business\Command;

use App\Runner\Business\Command\NachatPodgotovkuUnita\ContainerSettings;

final class NachatPodgotovkuUnita
{
    public function __construct(
        public readonly string $ProjectId,
        public readonly string $ProjectName,
        public readonly string $Id,
        public readonly string $Name,
        public readonly string $StorageUrl,
        public readonly array $Commands,
        public readonly array $Variables,
        public readonly array $Caches,
        public readonly ContainerSettings $ContainerSettings
    )
    {
    }
}
