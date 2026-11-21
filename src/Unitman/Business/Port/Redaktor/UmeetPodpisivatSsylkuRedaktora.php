<?php

declare(strict_types=1);

namespace App\Unitman\Business\Port\Redaktor;

/**
 * Подпись и проверка персональной ссылки редактора (HMAC).
 *
 * В ссылке нет личного токена: только `unitId`, `userId`, `mode` и `exp`.
 * Личный токен настраивается на раннере отдельно при подготовке сессии.
 */
interface UmeetPodpisivatSsylkuRedaktora
{
    /**
     * @param string $mode `view`|`edit`
     */
    public function podpisat(string $unitId, string $userId, string $mode, int $ttlSeconds): string;

    /**
     * @return array{unitId: string, userId: string, mode: string, exp: int}
     *
     * @throws \DomainException unit.redaktor.ssylka_nevalidna|unit.redaktor.ssylka_istekla
     */
    public function proverit(string $ssylka): array;
}
