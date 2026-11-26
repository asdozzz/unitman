<?php

declare(strict_types=1);

namespace App\Account\Business\Port;

/**
 * Отправка пользователю письма с разовым паролем.
 */
interface UmeetOtpravlyatVremenniyParol
{
    public function otpravit(string $email, ?string $nickname, string $temporaryPassword, string $locale): void;
}
