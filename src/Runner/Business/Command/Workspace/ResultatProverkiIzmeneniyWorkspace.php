<?php

declare(strict_types=1);

namespace App\Runner\Business\Command\Workspace;

final class ResultatProverkiIzmeneniyWorkspace
{
    /**
     * @param list<array{ProjectId: string, Name: string, Dirty: bool, Files: list<string>}> $Projects
     */
    public function __construct(
        public bool $Success,
        public array $Projects = [],
    ) {
    }
}
