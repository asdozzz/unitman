<?php

declare(strict_types=1);

namespace App\Account\Business\UseCase;

use App\Account\Business\Command\ActivateAccountByAdmin;
use App\Account\Business\Port\AccountRepository;
use App\Account\Business\Port\SecurityService;

final class ActivateAccountByAdminUseCase
{
    public function __construct(
        private SecurityService $securityService,
        private AccountRepository $accountRepository,
    ) {
    }

    public function handle(ActivateAccountByAdmin $command): void
    {
        if (!$this->securityService->isAdmin()) {
            throw new \Exception('security.access_denied');
        }

        $account = $this->accountRepository->getBy($command->accountId);
        $account->activateByAdmin();
        $this->accountRepository->save($account);
    }
}
