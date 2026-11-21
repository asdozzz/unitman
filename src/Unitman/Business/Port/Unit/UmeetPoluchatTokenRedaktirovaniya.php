<?php

declare(strict_types=1);

namespace App\Unitman\Business\Port\Unit;

/**
 * Выдача расшифрованного личного токена пользователя для редактирования юнита.
 *
 * Единственная точка внутри Unitman, где расшифрованный секрет покидает
 * агрегат `LichniyToken`. Используется только бэкендом (Acl-адаптеры
 * Runner/OpenCode) и никогда не попадает в HTTP-ответы.
 */
interface UmeetPoluchatTokenRedaktirovaniya
{
    /**
     * @throws \DomainException unit.redaktor.net_lichnogo_tokena
     * @throws \DomainException unit.redaktor.token_bez_prav_na_proekt
     */
    public function poluchitToken(string $unitId, string $accountId): string;
}
