<?php

namespace App\Account\Business\UseCase;

use App\Account\Business\Command\ChangeMyLocale;
use App\Account\Business\Port\AccountRepository;
use App\Account\Infra\Adapter\SymfonySecurityService;

final class ChangeMyLocaleUseCase
{
    public function __construct(private AccountRepository $accountRepository, private SymfonySecurityService $securityService)
    {
    }

    function handle(ChangeMyLocale $command): void
    {
        $currentId = $this->securityService->getCurrentUserId();
        $account = $this->accountRepository->getBy($currentId);
        $account->changeMyLocale($command);
        $this->accountRepository->save($account);
    }
}
