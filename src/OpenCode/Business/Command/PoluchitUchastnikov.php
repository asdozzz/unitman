<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Command;

/**
 * Список участников workspace-сессии.
 */
final readonly class PoluchitUchastnikov
{
    public function __construct(public string $workspaceId)
    {
    }
}
