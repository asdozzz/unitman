<?php

declare(strict_types=1);

namespace App\Unitman\Business\Port\Project;

interface UmeetPoluchatTokenDlyaProekta
{
    /**
     * Возвращает расшифрованный личный токен пользователя для проекта.
     *
     * @throws \DomainException unit.redaktor.net_lichnogo_tokena
     * @throws \DomainException unit.redaktor.token_bez_prav_na_proekt
     */
    public function poluchitTokenDlyProekta(string $projectId, string $accountId): string;
}
