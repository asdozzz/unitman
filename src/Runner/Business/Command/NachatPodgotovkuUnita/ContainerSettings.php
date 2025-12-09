<?php

namespace App\Runner\Business\Command\NachatPodgotovkuUnita;

final class ContainerSettings
{
    public function __construct(public readonly string $MemoryLimit = "3g")
    {
    }

}
