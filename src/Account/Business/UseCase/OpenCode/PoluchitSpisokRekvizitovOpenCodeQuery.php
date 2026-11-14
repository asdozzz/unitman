<?php

declare(strict_types=1);

namespace App\Account\Business\UseCase\OpenCode;

use App\Account\Business\Model\OpenCode\OpenCodeCredential;
use App\Account\Business\Port\OpenCodeCredentialRepository;
use App\Account\Business\Port\SecurityService;
use App\Account\Business\ReadModel\OpenCodeCredentialReadModel;

/**
 * Список реквизитов OpenCode текущего пользователя.
 *
 * Расшифровка не выполняется вовсе: для показа достаточно маски ключа.
 */
final class PoluchitSpisokRekvizitovOpenCodeQuery
{
    public function __construct(
        private SecurityService $securityService,
        private OpenCodeCredentialRepository $credentialRepository,
    ) {
    }

    /**
     * @return OpenCodeCredentialReadModel[]
     */
    public function handle(): array
    {
        $accountId = $this->securityService->getCurrentUserId();

        return array_map(
            static fn (OpenCodeCredential $credential): OpenCodeCredentialReadModel => new OpenCodeCredentialReadModel(
                $credential->getId(),
                $credential->getName(),
                $credential->getUrl(),
                $credential->getModel(),
                $credential->getApiKeyHint(),
                $credential->getCreatedAt(),
                $credential->getUpdatedAt(),
            ),
            $this->credentialRepository->findByAccountId($accountId),
        );
    }
}
