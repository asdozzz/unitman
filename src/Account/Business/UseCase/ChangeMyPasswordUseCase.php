<?php

namespace App\Account\Business\UseCase;

use App\Account\Business\Command\ChangeMyPassword;
use App\Account\Business\Model\JWTUser;
use App\Account\Business\Port\AccountRepository;
use App\Account\Business\Port\SecurityService;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

final class ChangeMyPasswordUseCase
{
    public function __construct(
        private SecurityService $securityService,
        private AccountRepository $accountRepository,
        private PasswordHasherFactoryInterface $passwordHasherFactory,
    )
    {
    }

    function handle(ChangeMyPassword $command): void
    {
        $currentUserId = $this->securityService->getCurrentUserId();

        $account = $this->accountRepository->getBy($currentUserId);
        $hasher = $this->passwordHasherFactory->getPasswordHasher(JWTUser::class);
        $password = $hasher->hash($command->newPassword);

        $account->changeMyPassword($password);
        $this->accountRepository->save($account);
    }
}
