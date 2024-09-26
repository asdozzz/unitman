<?php

namespace App\Account\Business\UseCase;

use App\Account\Business\Command\RegisterAccount;
use App\Account\Business\Model\Account;
use App\Account\Business\Port\AccountRepository;
use App\Account\Business\Port\CanHashPassword;
use App\Account\Business\Port\UmeetPoluchatAccountDlySystemi;
use App\Account\Business\Port\UuidGenerator;

final class RegisterSystemAccountUseCase
{
    public function __construct(
        private AccountRepository $accountRepository,
        private UuidGenerator $guidGenerator,
        private CanHashPassword $passwordHasher,
        private UmeetPoluchatAccountDlySystemi $umeetPoluchatAccountDlySystemi
    )
    {
    }
    function handle(): void
    {
        $id = $this->umeetPoluchatAccountDlySystemi->findSystemAccountId();

        if (!empty($id)) {
            throw new \DomainException('account.system_account_already_exists');
        }

        $password = $this->passwordHasher->hashPassword('system');
        $accountId = $this->guidGenerator->makeGuid();
        $account = Account::registerSystemAccount($accountId, $password);
        $this->accountRepository->save($account);
    }
}
