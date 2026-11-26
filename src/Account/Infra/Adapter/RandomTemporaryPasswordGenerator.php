<?php

declare(strict_types=1);

namespace App\Account\Infra\Adapter;

use App\Account\Business\Port\CanGenerateTemporaryPassword;

/**
 * Криптографически стойкий разовый пароль.
 *
 * Из набора исключены похожие символы (0/O, 1/l/I), чтобы пароль можно было
 * продиктовать или переписать из письма без ошибок.
 */
final class RandomTemporaryPasswordGenerator implements CanGenerateTemporaryPassword
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    private const LENGTH = 16;

    public function makeTemporaryPassword(): string
    {
        $maxIndex = strlen(self::ALPHABET) - 1;
        $password = '';

        for ($i = 0; $i < self::LENGTH; $i++) {
            $password .= self::ALPHABET[random_int(0, $maxIndex)];
        }

        return $password;
    }
}
