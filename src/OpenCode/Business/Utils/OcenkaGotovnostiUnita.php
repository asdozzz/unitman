<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Utils;

use App\OpenCode\Business\Model\SostoyanieUnita;

/**
 * Единая матрица готовности юнита для сессий OpenCode.
 *
 * Сессия может принимать сообщения только когда юнит дошёл до рабочего
 * состояния: успешно запущен или упал на любом этапе (с ошибкой тоже можно
 * работать в директории юнита). Всё остальное — ожидание конвейера.
 */
final class OcenkaGotovnostiUnita
{
    private const KOD_ZAPUSHEN = 'USPESHNO_ZAPUSHEN';
    private const KOD_UDALEN = 'UDALEN';
    private const KOD_UDALEN_VRUCHNUYU = 'UDALEN_VRUCHNUYU';
    private const PREFIX_OSHIBKA = 'OSHIBKA_';

    public function ocenit(bool $exists, ?string $code): string
    {
        if (!$exists) {
            return SostoyanieUnita::GROUP_ARCHIVED;
        }

        if ($code === null || $code === '') {
            return SostoyanieUnita::GROUP_BLOCKED;
        }

        if ($code === self::KOD_UDALEN || $code === self::KOD_UDALEN_VRUCHNUYU) {
            return SostoyanieUnita::GROUP_ARCHIVED;
        }

        if ($code === self::KOD_ZAPUSHEN) {
            return SostoyanieUnita::GROUP_READY;
        }

        if (str_starts_with($code, self::PREFIX_OSHIBKA)) {
            return SostoyanieUnita::GROUP_READY;
        }

        return SostoyanieUnita::GROUP_BLOCKED;
    }
}
