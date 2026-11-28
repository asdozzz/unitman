<?php

declare(strict_types=1);

namespace App\Skill\Business\Port;

/**
 * Авторизация операций над навыками.
 *
 * Порт закрывает два источника прав:
 *  - системная роль `ROLE_ADMIN` (глобальные навыки);
 *  - роль пользователя в проекте `ADMIN`/`USER` (проектные навыки).
 *
 * Реализация находится в Acl-слое и обращается к Account/Unitman только через
 * их публичные Api, не заглядывая во внутренние модели.
 */
interface PravaNavykov
{
    /**
     * @throws \DomainException `account.not_authenticated`, если пользователь не аутентифицирован
     */
    public function tekuschiiAccountId(): string;

    public function isAdmin(): bool;

    /**
     * Пользователь — `ADMIN` каждого из перечисленных проектов.
     *
     * @param list<string> $projectIds
     */
    public function estAdministratoromVsehProektov(array $projectIds, string $accountId): bool;

    /**
     * Пользователь состоит в хотя бы одном из перечисленных проектов
     * (`ADMIN` или `USER`).
     *
     * @param list<string> $projectIds
     */
    public function estChlenomHotyaByOdnogoProekta(array $projectIds, string $accountId): bool;
}
