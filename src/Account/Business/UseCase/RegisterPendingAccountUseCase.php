<?php

declare(strict_types=1);

namespace App\Account\Business\UseCase;

use App\Account\Business\Command\RegisterPendingAccount;
use App\Account\Business\Model\Account;
use App\Account\Business\Model\EmailVerification\EmailVerificationCode;
use App\Account\Business\Port\AccountRepository;
use App\Account\Business\Port\CanFindDouble;
use App\Account\Business\Port\CanGenerateEmailVerificationCode;
use App\Account\Business\Port\CanHashPassword;
use App\Account\Business\Port\CanHashVerificationCode;
use App\Account\Business\Port\CanSendEmailVerificationCode;
use App\Account\Business\Port\EmailVerificationCodeRepository;
use App\Account\Business\Port\UuidGenerator;

/**
 * Публичная саморегистрация.
 *
 * Аккаунт создаётся неподтверждённым и остаётся в ROLE_PENDING. На email
 * отправляется код; администраторы узнают о заявке только после того, как
 * пользователь докажет владение email.
 */
final class RegisterPendingAccountUseCase
{
    public function __construct(
        private AccountRepository $accountRepository,
        private CanHashPassword $passwordHasher,
        private CanFindDouble $canFindDouble,
        private UuidGenerator $uuidGenerator,
        private CanGenerateEmailVerificationCode $codeGenerator,
        private CanHashVerificationCode $codeHasher,
        private EmailVerificationCodeRepository $verificationCodeRepository,
        private CanSendEmailVerificationCode $codeSender,
        private int $emailVerificationTtl,
    ) {
    }

    public function handle(RegisterPendingAccount $command): string
    {
        if ($this->canFindDouble->isExistDoubleByEmail($command->getEmail())) {
            throw new \DomainException('account.email.double');
        }

        $command->setPassword($this->passwordHasher->hashPassword($command->getPassword()));

        $accountId = $this->uuidGenerator->makeGuid();
        $account = Account::requestRegistration($accountId, $command);
        $this->accountRepository->save($account);

        $now = time();
        $code = $this->codeGenerator->generate();
        $verification = new EmailVerificationCode(
            $accountId,
            $command->getEmail(),
            $this->codeHasher->hash($code),
            $now + $this->emailVerificationTtl,
            lastSentAt: $now,
        );
        $this->verificationCodeRepository->save($verification);

        try {
            $this->codeSender->otpravit(
                $command->getEmail(),
                $command->nickname,
                $code,
                $command->getLocale(),
            );
        } catch (\Throwable) {
            throw new \DomainException('account.email.send_failed');
        }

        return $accountId;
    }
}
