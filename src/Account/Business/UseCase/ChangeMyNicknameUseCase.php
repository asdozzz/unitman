<?php

namespace App\Account\Business\UseCase;

use App\Account\Business\Command\ChangeMyNickname;
use App\Account\Business\Port\AccountRepository;
use App\Account\Business\Port\SecurityService;

final class ChangeMyNicknameUseCase
{
    public function __construct(
        private SecurityService $securityService,
        private AccountRepository $accountRepository,
    )
    {
    }

    function handle(ChangeMyNickname $command): void
    {
        $currentUserId = $this->securityService->getCurrentUserId();

        $account = $this->accountRepository->getBy($currentUserId);
        $account->changeMyNickname($command->newNickname);
        $this->accountRepository->save($account);
    }
}
