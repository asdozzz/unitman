<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Utils;

/**
 * Токен владельца захвата юнита для хода OpenCode.
 *
 * Захват и освобождение выполняются в разных местах (старт хода и опрос
 * результата), поэтому формат токена задаётся в одном месте. Токен непрозрачен
 * для Unitman: по нему нельзя освободить чужой захват, и по нему видно,
 * что юнит занят именно ходом OpenCode.
 */
final class VladelecZanyatostiOpenCode
{
    private const PREFIX = 'opencode:';

    public static function dlyaHoda(string $turnId): string
    {
        return self::PREFIX.trim($turnId);
    }
}
