<?php

declare(strict_types=1);

namespace App\Runner\Business\Command;

/**
 * Подготовка редакторской сессии юнита (plan §8).
 *
 * На провод уходят `UnitId`, `UserId`, `Mode` (`view`|`edit`) и личный токен
 * пользователя `Token`. Токен не попадает в ссылку и в `.git/config`: раннер
 * настраивает RW-доступ и git-креденшелы под пользователя на время сессии.
 * Для `view`-сессии токен не используется, но контракт оставляет поле
 * единообразным.
 *
 * `UserName`/`UserEmail` необязательны и служат для настройки git-идентичности
 * коммитов (решение 15.12-а); старый раннер их игнорирует.
 */
final class PodgotovitSessiyuRedaktora
{
    public function __construct(
        public readonly string $UnitId,
        public readonly string $UserId,
        public readonly string $Mode,
        public readonly string $Token,
        public readonly ?string $UserName = null,
        public readonly ?string $UserEmail = null,
    )
    {
    }
}
