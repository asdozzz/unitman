<?php

declare(strict_types=1);

namespace App\Runner\Business\Model;

/**
 * Режим редактора юнита в контракте с раннером (plan §8).
 *
 * На проводе значения — строгие строки `view`/`edit`: сами команды хранят
 * строку, чтобы JSON-пейлоад оставался плоским и совместимым с Go-раннером,
 * а enum используется для валидации и нормализации.
 *
 * `locked` вычисляется в Unitman как `view` и отдельным значением раннера
 * не передаётся.
 */
enum RezhimRedaktora: string
{
    case View = 'view';
    case Edit = 'edit';

    public static function isValid(string $value): bool
    {
        return self::tryFrom($value) !== null;
    }

    /**
     * Нормализация необязательного значения: отсутствие или неизвестное
     * значение трактуется как `view`. Это сохраняет обратную совместимость
     * со старыми вызовами, которые новое поле не передают.
     */
    public static function normalize(?string $value): string
    {
        return self::tryFrom((string) $value)?->value ?? self::View->value;
    }
}
