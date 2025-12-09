<?php

namespace App\Unitman\Business\Command\Project;

use App\Utils\Converter\JsonBodySerializableInterface;

final class AddProject implements JsonBodySerializableInterface
{
    public function __construct(
        public readonly string $repoId,
        public readonly string $projectCode,
        public readonly string $projectName,
        public readonly string $mainBranch = 'master',
        public readonly string $proxyHost = '',
        public readonly int $memoryLimit = 0
    )
    {
    }

}
