<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Command;

/**
 * Приглашение участника в workspace-сессию.
 */
final readonly class DobavitUchastnika
{
    public function __construct(
        public string $workspaceId,
        public string $accountId,
        public string $role,
        public ?string $credentialId = null,
        public ?string $model = null,
    ) {
    }
}
