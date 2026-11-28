<?php

declare(strict_types=1);

namespace App\Skill\Business\Utils;

use App\Skill\Business\Model\NavykOpenCode;
use App\Skill\Business\Port\PravaNavykov;

/**
 * Политика доступа к навыкам.
 *
 * - глобальные навыки читает любой аутентифицированный пользователь,
 *   изменяет только системный администратор (`ROLE_ADMIN`);
 * - проектный навык читает участник хотя бы одного привязанного проекта,
 *   изменяет администратор КАЖДОГО привязанного проекта;
 * - системный администратор дополнительно получает право на изменение
 *   проектного навыка (административный обход).
 */
final class ProverkaPravNavykov
{
    public function __construct(private readonly PravaNavykov $prava)
    {
    }

    public function tekuschiiAccountId(): string
    {
        return $this->prava->tekuschiiAccountId();
    }

    /**
     * @throws \DomainException `skill.forbidden`
     */
    public function trebovatGlobalnogoAdministratora(): string
    {
        $accountId = $this->prava->tekuschiiAccountId();

        if (!$this->prava->isAdmin()) {
            throw new \DomainException('skill.forbidden');
        }

        return $accountId;
    }

    /**
     * @param list<string> $projectIds
     *
     * @throws \DomainException `skill.forbidden`
     */
    public function trebovatAdministratoraProektov(array $projectIds): string
    {
        $accountId = $this->prava->tekuschiiAccountId();

        if ($this->prava->isAdmin()) {
            return $accountId;
        }

        if ($projectIds === [] || !$this->prava->estAdministratoromVsehProektov($projectIds, $accountId)) {
            throw new \DomainException('skill.forbidden');
        }

        return $accountId;
    }

    /**
     * Чтение проектного навыка: членство хотя бы в одном проекте либо
     * системный администратор.
     *
     * @param list<string> $projectIds
     *
     * @throws \DomainException `skill.forbidden`
     */
    public function trebovatChtenieProektnogo(array $projectIds): string
    {
        $accountId = $this->prava->tekuschiiAccountId();

        if ($this->prava->isAdmin()) {
            return $accountId;
        }

        if ($projectIds === [] || !$this->prava->estChlenomHotyaByOdnogoProekta($projectIds, $accountId)) {
            throw new \DomainException('skill.forbidden');
        }

        return $accountId;
    }

    public function mozhetRedaktirovat(NavykOpenCode $navyk, string $accountId): bool
    {
        if ($this->prava->isAdmin()) {
            return true;
        }

        if ($navyk->isGlobal()) {
            return false;
        }

        $projectIds = $navyk->getProjectIds();

        return $projectIds !== []
            && $this->prava->estAdministratoromVsehProektov($projectIds, $accountId);
    }

    /**
     * @throws \DomainException `skill.forbidden`
     */
    public function trebovatRedaktirovanie(NavykOpenCode $navyk, string $accountId): void
    {
        if (!$this->mozhetRedaktirovat($navyk, $accountId)) {
            throw new \DomainException('skill.forbidden');
        }
    }

    /**
     * @throws \DomainException `skill.forbidden`
     */
    public function trebovatChtenie(NavykOpenCode $navyk, string $accountId): void
    {
        if ($navyk->isGlobal()) {
            return;
        }

        if ($this->prava->isAdmin()) {
            return;
        }

        $projectIds = $navyk->getProjectIds();

        if ($projectIds === [] || !$this->prava->estChlenomHotyaByOdnogoProekta($projectIds, $accountId)) {
            throw new \DomainException('skill.forbidden');
        }
    }
}
