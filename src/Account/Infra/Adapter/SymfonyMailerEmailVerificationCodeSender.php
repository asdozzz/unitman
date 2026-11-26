<?php

declare(strict_types=1);

namespace App\Account\Infra\Adapter;

use App\Account\Business\Port\CanSendEmailVerificationCode;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Отправляет код подтверждения email.
 */
final class SymfonyMailerEmailVerificationCodeSender implements CanSendEmailVerificationCode
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly TranslatorInterface $translator,
        private readonly string $mailerFromAddress,
        private readonly string $mailerFromName,
        private readonly int $emailVerificationTtl,
    ) {
    }

    public function otpravit(string $email, ?string $nickname, string $code, string $locale): void
    {
        $name = $nickname !== null && trim($nickname) !== '' ? $nickname : $email;
        $minutes = max(1, intdiv($this->emailVerificationTtl, 60));

        $message = (new TemplatedEmail())
            ->from(new Address($this->mailerFromAddress, $this->mailerFromName))
            ->to($email)
            ->subject($this->translator->trans('email.email_verification.subject', [], 'messages', $locale))
            ->htmlTemplate('email/email_verification_code.html.twig')
            ->textTemplate('email/email_verification_code.txt.twig')
            ->context([
                'locale' => $locale,
                'greeting' => $this->translator->trans(
                    'email.email_verification.greeting',
                    ['%name%' => $name],
                    'messages',
                    $locale,
                ),
                'intro' => $this->translator->trans('email.email_verification.intro', [], 'messages', $locale),
                'codeLabel' => $this->translator->trans('email.email_verification.code_label', [], 'messages', $locale),
                'code' => $code,
                'expires' => $this->translator->trans(
                    'email.email_verification.expires',
                    ['%minutes%' => $minutes],
                    'messages',
                    $locale,
                ),
                'outro' => $this->translator->trans('email.email_verification.outro', [], 'messages', $locale),
            ]);

        $this->mailer->send($message);
    }
}
