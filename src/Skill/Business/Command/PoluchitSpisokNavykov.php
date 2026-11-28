<?php

declare(strict_types=1);

namespace App\Skill\Business\Command;

final class PoluchitSpisokNavykov
{
    public function __construct(
        public readonly ?string $scope = null,
        public readonly ?string $projectId = null,
        public readonly bool $includeArchived = false,
    ) {
    }
}
