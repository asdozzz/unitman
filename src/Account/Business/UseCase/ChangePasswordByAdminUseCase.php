<?php

namespace App\Account\Business\UseCase;

use App\Account\Business\Command\ChangePasswordByAdmin;
use App\Account\Business\Model\JWTUser;
use App\Account\Business\Port\AccountRepository;
use App\Account\Business\Port\SecurityService;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

final class ChangePasswordByAdminUseCase
{
    public function __construct(
        private SecurityService $securityService,
        private AccountRepository $accountRepository,
        private PasswordHasherFactoryInterface $passwordHasherFactory,
    )
    {
    }

    function handle(ChangePasswordByAdmin $command): void
    {
        if (!$this->securityService->isAdmin()) {
            throw new \RuntimeException('security.access_denied');
        }

        $account = $this->accountRepository->getBy($command->accountId);

        $hasher = $this->passwordHasherFactory->getPasswordHasher(JWTUser::class);
        $password = $hasher->hash($command->newPassword);

        $account->changePasswordByAdmin($password);
        $this->accountRepository->save($account);
    }
}
