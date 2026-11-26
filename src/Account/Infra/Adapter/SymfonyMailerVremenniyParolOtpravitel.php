<?php

declare(strict_types=1);

namespace App\Account\Infra\Adapter;

use App\Account\Business\Port\UmeetOtpravlyatVremenniyParol;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Отправляет пользователю письмо с разовым паролем.
 *
 * Текст письма локализуется по локали аккаунта; шаблон общий, а строки
 * приходят из `messages.*.php`, чтобы письмо не дублировало переводы.
 */
final class SymfonyMailerVremenniyParolOtpravitel implements UmeetOtpravlyatVremenniyParol
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly TranslatorInterface $translator,
        private readonly string $mailerFromAddress,
        private readonly string $mailerFromName,
    ) {
    }

    public function otpravit(string $email, ?string $nickname, string $temporaryPassword, string $locale): void
    {
        $name = $nickname !== null && trim($nickname) !== '' ? $nickname : $email;

        $message = (new TemplatedEmail())
            ->from(new Address($this->mailerFromAddress, $this->mailerFromName))
            ->to($email)
            ->subject($this->translator->trans('email.temporary_password.subject', [], 'messages', $locale))
            ->htmlTemplate('email/temporary_password.html.twig')
            ->textTemplate('email/temporary_password.txt.twig')
            ->context([
                'locale' => $locale,
                'greeting' => $this->translator->trans(
                    'email.temporary_password.greeting',
                    ['%name%' => $name],
                    'messages',
                    $locale,
                ),
                'intro' => $this->translator->trans('email.temporary_password.intro', [], 'messages', $locale),
                'passwordLabel' => $this->translator->trans('email.temporary_password.password_label', [], 'messages', $locale),
                'password' => $temporaryPassword,
                'outro' => $this->translator->trans('email.temporary_password.outro', [], 'messages', $locale),
            ]);

        $this->mailer->send($message);
    }
}
