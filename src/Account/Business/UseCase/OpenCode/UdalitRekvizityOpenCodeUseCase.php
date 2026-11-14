<?php

declare(strict_types=1);

namespace App\Account\Business\UseCase\OpenCode;

use App\Account\Business\Command\UdalitRekvizityOpenCode;
use App\Account\Business\Port\OpenCodeCredentialRepository;
use App\Account\Business\Port\SecurityService;

/**
 * Удаление реквизитов OpenCode текущего пользователя.
 */
final class UdalitRekvizityOpenCodeUseCase
{
    public function __construct(
        private SecurityService $securityService,
        private OpenCodeCredentialRepository $credentialRepository,
    ) {
    }

    public function handle(UdalitRekvizityOpenCode $command): void
    {
        $accountId = $this->securityService->getCurrentUserId();

        if (trim($command->id) === '') {
            throw new \DomainException('account.opencode_credential.empty_id');
        }

        $credential = $this->credentialRepository->getById(trim($command->id));
        $credential->trebovatVladelca($accountId);

        $this->credentialRepository->delete($credential->getId());
    }
}
