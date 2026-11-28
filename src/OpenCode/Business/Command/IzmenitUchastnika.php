<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Command;

/**
 * Смена роли и реквизитов по умолчанию участника workspace.
 */
final readonly class IzmenitUchastnika
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
