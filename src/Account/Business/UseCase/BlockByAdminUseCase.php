<?php

namespace App\Account\Business\UseCase;

use App\Account\Business\Command\BlockByAdmin;
use App\Account\Business\Port\AccountRepository;
use App\Account\Business\Port\RefreshTokenRepository;

final class BlockByAdminUseCase
{
    public function __construct(
        private AccountRepository $accountRepository,
        private RefreshTokenRepository $refreshTokenRepository,
    )
    {
    }

    function handle(BlockByAdmin $command): void
    {
        $account = $this->accountRepository->getBy($command->accountId);
        $account->blockByAdmin($command->reason);
        $this->accountRepository->save($account);

        // Заблокированный аккаунт не должен иметь возможности продлевать сессию.
        $this->refreshTokenRepository->revokeAllByAccountId($command->accountId);
    }
}
