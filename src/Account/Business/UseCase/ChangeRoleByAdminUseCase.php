<?php

namespace App\Account\Business\UseCase;

use App\Account\Business\Command\ChangeRoleByAdmin;
use App\Account\Business\Port\AccountRepository;
use App\Account\Business\Port\SecurityService;

final class ChangeRoleByAdminUseCase
{
    public function __construct(
        private SecurityService $securityService,
        private AccountRepository $accountRepository
    )
    {
    }

    function handle(ChangeRoleByAdmin $command): void
    {
        if (!$this->securityService->isAdmin()) {
            throw new \RuntimeException('security.access_denied');
        }

        $account = $this->accountRepository->getBy($command->accountId);
        $account->changeRoleByAdmin($command->newRole);
        $this->accountRepository->save($account);
    }
}
