<?php

declare(strict_types=1);

namespace App\Account\Business\UseCase;

use App\Account\Business\Command\ResendEmailVerificationCode;
use App\Account\Business\Port\AccountRepository;
use App\Account\Business\Port\CanGenerateEmailVerificationCode;
use App\Account\Business\Port\CanHashVerificationCode;
use App\Account\Business\Port\CanSendEmailVerificationCode;
use App\Account\Business\Port\EmailVerificationCodeRepository;

final class ResendEmailVerificationCodeUseCase
{
    private const SENT_COUNT_WINDOW_SECONDS = 3600;
    private const MAX_SENT_PER_WINDOW = 5;

    public function __construct(
        private EmailVerificationCodeRepository $verificationCodeRepository,
        private CanGenerateEmailVerificationCode $codeGenerator,
        private CanHashVerificationCode $codeHasher,
        private CanSendEmailVerificationCode $codeSender,
        private AccountRepository $accountRepository,
        private int $emailVerificationTtl,
        private int $emailVerificationResendCooldown,
    ) {
    }

    public function handle(ResendEmailVerificationCode $command): void
    {
        $record = $this->verificationCodeRepository->findByEmail(trim($command->email));

        // Не раскрываем, существует ли аккаунт: для неизвестного email
        // возвращаем нейтральный успех.
        if ($record === null || $record->isConsumed()) {
            return;
        }

        $account = $this->accountRepository->getBy($record->accountId);

        if ($account->isEmailVerified()) {
            return;
        }

        $now = time();

        if ($now - $record->lastSentAt < $this->emailVerificationResendCooldown) {
            throw new \DomainException('account.email.resend_too_often');
        }

        $sentCount = $now - $record->lastSentAt >= self::SENT_COUNT_WINDOW_SECONDS
            ? 1
            : $record->sentCount + 1;

        if ($sentCount > self::MAX_SENT_PER_WINDOW) {
            throw new \DomainException('account.email.too_many_resends');
        }

        $code = $this->codeGenerator->generate();
        $updated = $record->withNewCode(
            $this->codeHasher->hash($code),
            $now + $this->emailVerificationTtl,
            $now,
            $sentCount,
        );
        $this->verificationCodeRepository->save($updated);

        $nickname = $account->getNickname();

        $this->codeSender->otpravit(
            $record->email,
            $nickname !== '' ? $nickname : null,
            $code,
            $account->getLocale(),
        );
    }
}
