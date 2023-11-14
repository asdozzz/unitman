<?php

namespace App\Account\Business\UseCase;

use App\Account\Business\Command\BlockByAdmin;
use App\Account\Business\Port\AccountRepository;

final class BlockByAdminUseCase
{
    public function __construct(
        private AccountRepository $accountRepository,
    )
    {
    }

    function handle(BlockByAdmin $command): void
    {
        $account = $this->accountRepository->getBy($command->accountId);
        $account->blockByAdmin($command->reason);
        $this->accountRepository->save($account);
    }
}
