<?php

namespace App\Runner\Business\Command;

final class NachatOchistkuProekta
{
    public function __construct(
        public readonly string $ProjectId,
        public readonly string $ProjectName,
        public readonly string $StorageUrl,
    )
    {
    }
}
