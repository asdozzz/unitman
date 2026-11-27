<?php

declare(strict_types=1);

namespace App\Runner\Business\Command\Workspace;

final class ResultatObnovleniyaWorkspace
{
    /**
     * @param list<array{ProjectId: string, Name: string, Success: bool, ErrorKey: string|null}> $Projects
     * @param list<array{FileId: string, Name: string, Success: bool, ErrorKey: string|null}>    $Files
     */
    public function __construct(
        public int $Success,
        public array $Steps = [],
        public string $ResponseId = '',
        public array $Projects = [],
        public array $Files = [],
    ) {
    }
}
