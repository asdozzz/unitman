<?php

namespace App\Account\Business\UseCase;

use App\Account\Business\Command\ChangeEmailByAdmin;
use App\Account\Business\Port\AccountRepository;
use App\Account\Business\Port\CanFindDouble;
use App\Account\Business\Port\SecurityService;

final class ChangeEmailByAdminUseCase
{
    public function __construct(
        private SecurityService $securityService,
        private AccountRepository $accountRepository,
        private CanFindDouble $canFindDouble
    )
    {
    }

    public function handle(ChangeEmailByAdmin $command): void
    {
        if (!$this->securityService->isAdmin()) {
            throw new \RuntimeException('security.access_denied');
        }

        $account = $this->accountRepository->getBy($command->accountId);

        if ($this->canFindDouble->isExistDoubleByEmail($command->newEmail)) {
            throw new \RuntimeException('account.email.double');
        }

        $account->changeEmailByAdmin($command->newEmail);
        $this->accountRepository->save($account);
    }
}
