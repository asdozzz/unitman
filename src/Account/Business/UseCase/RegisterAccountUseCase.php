<?php

namespace App\Account\Business\UseCase;

use App\Account\Business\Command\RegisterAccount;
use App\Account\Business\Model\Account;
use App\Account\Business\Port\AccountRepository;
use App\Account\Business\Port\CanFindDouble;
use App\Account\Business\Port\CanGenerateTemporaryPassword;
use App\Account\Business\Port\CanHashPassword;
use App\Account\Business\Port\SecurityService;
use App\Account\Business\Port\UmeetOtpravlyatVremenniyParol;
use App\Account\Business\Port\UuidGenerator;

/**
 * Добавление аккаунта администратором.
 *
 * Аккаунт создаётся сразу активным, а разовый пароль генерирует система и
 * отправляет пользователю письмом — администратор пароль не задаёт.
 */
final class RegisterAccountUseCase
{
    public function __construct(
        private SecurityService $securityService,
        private AccountRepository $accountRepository,
        private CanHashPassword $passwordHasher,
        private CanFindDouble $canFindDouble,
        private UuidGenerator $uuidGenerator,
        private CanGenerateTemporaryPassword $temporaryPasswordGenerator,
        private UmeetOtpravlyatVremenniyParol $temporaryPasswordSender,
    )
    {
    }

    function handle(RegisterAccount $registerAccount): string
    {
        if (!$this->securityService->isAdmin()) {
            throw new \Exception('security.access_denied');
        }

        if ($this->canFindDouble->isExistDoubleByEmail($registerAccount->getEmail())) {
            throw new \DomainException('account.email.double');
        }

        $temporaryPassword = $this->temporaryPasswordGenerator->makeTemporaryPassword();
        $accountId = $this->uuidGenerator->makeGuid();

        $account = Account::registerActiveAccount(
            $accountId,
            $registerAccount->getEmail(),
            $this->passwordHasher->hashPassword($temporaryPassword),
            $registerAccount->getRoles(),
            $registerAccount->getLocale(),
            $registerAccount->nickname ?? '',
        );
        $this->accountRepository->save($account);

        $this->temporaryPasswordSender->otpravit(
            $registerAccount->getEmail(),
            $registerAccount->nickname,
            $temporaryPassword,
            $registerAccount->getLocale(),
        );

        return $accountId;
    }
}
