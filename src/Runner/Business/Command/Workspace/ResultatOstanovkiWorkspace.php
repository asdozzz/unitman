<?php

declare(strict_types=1);

namespace App\Runner\Business\Command\Workspace;

final class ResultatOstanovkiWorkspace
{
    /**
     * @param list<array{Command: string, Response: string, Success: bool, Unixtime: int}> $Steps
     */
    public function __construct(
        public int $Success,
        public array $Steps = [],
        public string $ResponseId = '',
    ) {
    }
}
