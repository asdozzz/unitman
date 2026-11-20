<?php

declare(strict_types=1);

namespace App\Runner\Business\Command\Workspace;

final class OstanovitWorkspace
{
    public function __construct(public readonly string $WorkspaceId)
    {
    }
}
