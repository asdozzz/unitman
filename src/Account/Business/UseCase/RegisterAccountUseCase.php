<?php

namespace App\Account\Business\UseCase;

use App\Account\Business\Command\RegisterAccount;
use App\Account\Business\Model\Account;
use App\Account\Business\Port\AccountRepository;
use App\Account\Business\Port\CanFindDouble;
use App\Account\Business\Port\CanHashPassword;
use App\Account\Business\Port\SecurityService;
use App\Account\Business\Port\UuidGenerator;

final class RegisterAccountUseCase
{
    public function __construct(
        private SecurityService $securityService,
        private AccountRepository $accountRepository,
        private CanHashPassword $passwordHasher,
        private CanFindDouble $canFindDouble,
        private UuidGenerator $uuidGenerator
    )
    {
    }

    function handle(RegisterAccount $registerAccount): void
    {
        if (!$this->securityService->isAdmin()) {
            throw new \Exception('security.access_denied');
        }

        $registerAccount->setPassword($this->passwordHasher->hashPassword($registerAccount->getPassword()));
        $accountId = $this->uuidGenerator->makeGuid();

        if ($this->canFindDouble->isExistDoubleByEmail($registerAccount->getEmail())) {
            throw new \DomainException('account.email.double');
        }

        $account = Account::registerAccount($accountId, $registerAccount);
        $this->accountRepository->save($account);
    }
}
