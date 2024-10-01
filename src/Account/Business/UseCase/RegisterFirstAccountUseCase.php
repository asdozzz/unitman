<?php

namespace App\Account\Business\UseCase;

use App\Account\Business\Command\RegisterAccount;
use App\Account\Business\Command\RegisterFirstAccount;
use App\Account\Business\Model\Account;
use App\Account\Business\Port\AccountRepository;
use App\Account\Business\Port\CanHashPassword;
use App\Account\Business\Port\UuidGenerator;

final class RegisterFirstAccountUseCase
{
    public function __construct(
        private AccountRepository $accountRepository,
        private UuidGenerator $guidGenerator,
        private CanHashPassword $passwordHasher,
        private string $defaultLocale
    )
    {
    }

    public function handle(RegisterFirstAccount $command): void
    {
        $password = $this->passwordHasher->hashPassword($command->password);
        $registerAccount = new RegisterAccount($command->email, $password, Account\Role::ROLE_ADMIN->value, $this->defaultLocale);
        $accountId = $this->guidGenerator->makeGuid();
        $account = Account::registerAccount($accountId, $registerAccount);
        $this->accountRepository->save($account);
    }
}
