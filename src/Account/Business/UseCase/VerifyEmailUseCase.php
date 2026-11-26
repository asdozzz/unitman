<?php

declare(strict_types=1);

namespace App\Account\Business\UseCase;

use App\Account\Business\Command\VerifyEmail;
use App\Account\Business\Port\AccountRepository;
use App\Account\Business\Port\CanHashVerificationCode;
use App\Account\Business\Port\EmailVerificationCodeRepository;
use App\Account\Business\Port\UmeetOpoveshatOZaproseAktivacii;

final class VerifyEmailUseCase
{
    public function __construct(
        private EmailVerificationCodeRepository $verificationCodeRepository,
        private CanHashVerificationCode $codeHasher,
        private AccountRepository $accountRepository,
        private UmeetOpoveshatOZaproseAktivacii $activationRequestNotifier,
        private int $emailVerificationMaxAttempts,
    ) {
    }

    public function handle(VerifyEmail $command): void
    {
        $now = time();
        $record = $this->verificationCodeRepository->findByEmail(trim($command->email));

        if ($record === null || $record->isConsumed()) {
            throw new \DomainException('account.email.code_invalid');
        }

        if ($record->isExpired($now)) {
            throw new \DomainException('account.email.code_expired');
        }

        if ($record->attempts >= $this->emailVerificationMaxAttempts) {
            throw new \DomainException('account.email.too_many_attempts');
        }

        if (!$this->codeHasher->verify(trim($command->code), $record->codeHash)) {
            $this->verificationCodeRepository->save($record->withIncrementedAttempts());
            throw new \DomainException('account.email.code_invalid');
        }

        $account = $this->accountRepository->getBy($record->accountId);
        $wasVerified = $account->isEmailVerified();

        if (!$wasVerified) {
            $account->confirmEmail();
            $this->accountRepository->save($account);
        }

        $this->verificationCodeRepository->consume($record, $now);

        if (!$wasVerified) {
            $nickname = $account->getNickname();

            $this->activationRequestNotifier->zaproshena(
                $account->getId(),
                $account->getEmail(),
                $nickname !== '' ? $nickname : null,
            );
        }
    }
}
