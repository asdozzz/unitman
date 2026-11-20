<?php

declare(strict_types=1);

namespace App\Runner\Business\Command\Workspace;

final class ResultatZapuskaWorkspace
{
    /**
     * @param list<array{ProjectId: string, Name: string, Success: bool, ErrorKey: string|null}> $Projects
     */
    public function __construct(
        public int $Success,
        public array $Steps = [],
        public string $ResponseId = '',
        public array $Projects = [],
    ) {
    }
}
