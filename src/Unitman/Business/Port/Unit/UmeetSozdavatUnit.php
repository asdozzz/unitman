<?php

declare(strict_types=1);

namespace App\Unitman\Business\Port\Unit;

use App\Unitman\Business\Command\Unit\SozdatUnit;

/**
 * Создание юнита от имени явно переданного пользователя.
 *
 * Отдельный порт нужен групповому сценарию: там юнит создаётся с заранее
 * зарезервированными id и уже проверенными связями.
 */
interface UmeetSozdavatUnit
{
    public function sozdatDlyaPolzovatelya(
        SozdatUnit $command,
        string $userId,
        ?string $unitId = null,
        ?string $prozesId = null,
        bool $proveritSvyazi = true,
        ?string $runnerId = null,
    ): string;
}
