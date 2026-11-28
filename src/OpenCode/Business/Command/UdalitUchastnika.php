<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Command;

/**
 * Удаление участника из workspace-сессии.
 */
final readonly class UdalitUchastnika
{
    public function __construct(
        public string $workspaceId,
        public string $accountId,
    ) {
    }
}
