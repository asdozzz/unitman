<?php

namespace App\Account\Business\UseCase;

use App\Account\Business\Command\BlockByAdmin;
use App\Account\Business\Command\UnblockByAdmin;
use App\Account\Business\Port\AccountRepository;

final class UnblockByAdminUseCase
{
    public function __construct(
        private AccountRepository $accountRepository,
    )
    {
    }

    function handle(UnblockByAdmin $command): void
    {
        $account = $this->accountRepository->getBy($command->accountId);
        $account->unblockByAdmin($command->reason);
        $this->accountRepository->save($account);
    }
}
