<?php

declare(strict_types=1);

namespace App\Runner\Business\Command;

/**
 * Переключение режима IDE-сайдкара уже подготовленного юнита (plan §8).
 *
 * На провод уходят `UnitId` и `Mode` (`view`|`edit`). Команда вызывается
 * переключателем редактирования, когда IDE-сайдкар уже существует; раннер
 * пересоздаёт сайдкар с новым режимом доступа к файлам проекта.
 */
final class IzmenitRezhimRedaktoraUnita
{
    public function __construct(
        public readonly string $UnitId,
        public readonly string $Mode = 'view',
        public readonly ?string $RunnerId = null,
        public readonly ?string $TaskQueue = null,
    ) {
    }
}
