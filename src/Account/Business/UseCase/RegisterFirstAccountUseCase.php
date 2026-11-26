<?php

namespace App\Account\Business\UseCase;

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
        $accountId = $this->guidGenerator->makeGuid();
        $account = Account::registerActiveAccount(
            $accountId,
            $command->email,
            $password,
            Account\Role::ROLE_ADMIN->value,
            $this->defaultLocale,
        );
        $this->accountRepository->save($account);
    }
}
