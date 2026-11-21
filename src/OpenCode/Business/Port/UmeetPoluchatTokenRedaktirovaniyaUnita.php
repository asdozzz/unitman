<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Port;

/**
 * Выдача расшифрованного личного токена владельца сессии для RW-хода.
 *
 * Единственная точка, где OpenCode получает секрет: токен передаётся только
 * в контейнер команды раннера и никогда не попадает в события, логи или
 * HTTP-ответы. Для read-only ходов метод не вызывается вовсе.
 */
interface UmeetPoluchatTokenRedaktirovaniyaUnita
{
    /**
     * @throws \DomainException unit.redaktor.net_lichnogo_tokena
     * @throws \DomainException unit.redaktor.token_bez_prav_na_proekt
     */
    public function poluchitTokenDlyRedaktirovaniyaUnita(string $unitId, string $accountId): string;
}
