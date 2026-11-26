<?php

namespace App\Runner\Business\Command;

final class NachatObnovlenieUnita
{
    public function __construct(
        public readonly string $ProjectId,
        public readonly string $ProjectName,
        public readonly string $Id,
        public readonly string $Name,
        public readonly string $StorageUrl,
        public readonly ?string $RunnerId = null,
        public readonly ?string $TaskQueue = null,
        public readonly string $RepoId = '',
    )
    {
    }
}
