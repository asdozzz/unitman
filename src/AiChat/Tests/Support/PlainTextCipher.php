<?php

declare(strict_types=1);

namespace App\AiChat\Tests\Support;

use App\Account\Business\Port\UmeetShifrovatSekrety;

/**
 * Тестовая «шифровка»: возвращает вход без изменений.
 *
 * Позволяет передать в OpenCodeCredential заранее известный API-ключ и
 * проверить, что AccountApi расшифровывает его и прокси уводит в заголовок.
 */
final class PlainTextCipher implements UmeetShifrovatSekrety
{
    public function zashifrovat(string $otkrytyiTekst, string $aad): string
    {
        return $otkrytyiTekst;
    }

    public function rasshifrovat(string $shifrotekst, string $aad): string
    {
        return $shifrotekst;
    }
}
