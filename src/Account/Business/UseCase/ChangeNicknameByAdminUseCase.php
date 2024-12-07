<?php

namespace App\Account\Business\UseCase;

use App\Account\Business\Command\ChangeEmailByAdmin;
use App\Account\Business\Command\ChangeNicknameByAdmin;
use App\Account\Business\Port\AccountRepository;
use App\Account\Business\Port\CanFindDouble;
use App\Account\Business\Port\SecurityService;

final class ChangeNicknameByAdminUseCase
{
    public function __construct(
        private SecurityService $securityService,
        private AccountRepository $accountRepository,
        private CanFindDouble $canFindDouble
    )
    {
    }

    public function handle(ChangeNicknameByAdmin $command): void
    {
        if (!$this->securityService->isAdmin()) {
            throw new \RuntimeException('security.access_denied');
        }

        $account = $this->accountRepository->getBy($command->accountId);

        if ($this->canFindDouble->isExistDoubleByNickname($command->accountId, $command->newNickname)) {
            throw new \RuntimeException('account.nickname.double');
        }

        $account->changeNicknameByAdmin($command->newNickname);
        $this->accountRepository->save($account);
    }
}
