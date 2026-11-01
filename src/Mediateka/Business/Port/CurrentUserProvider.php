<?php

declare(strict_types=1);

namespace App\Mediateka\Business\Port;

/**
 * Порт получения идентификатора текущего пользователя.
 * Нужен, чтобы модуль не зависел от модуля Account: реализация —
 * {@see \App\Mediateka\Acl\Adapter\AccountAdapter}, которая обращается
 * к публичному API Account (AccountApi).
 *
 * Пользователь всегда есть: если он не аутентифицирован, модуль Account
 * бросает исключение, поэтому null-результата у метода нет.
 */
interface CurrentUserProvider
{
    public function getCurrentUserId(): string;
}
