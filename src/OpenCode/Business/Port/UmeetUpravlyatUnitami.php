<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Port;

use App\OpenCode\Business\Model\ParametriSozdaniyaUnita;
use App\OpenCode\Business\Model\SozdanniyUnit;
use App\OpenCode\Business\Model\SostoyanieUnita;

/**
 * Управление юнитами из сессий OpenCode: состояние, доступ, создание
 * и продвижение по конвейеру. Реализация — Acl-адаптер над UnitmanApi.
 */
interface UmeetUpravlyatUnitami
{
    public function poluchitSostoyanieUnita(string $unitId): SostoyanieUnita;

    public function proveritDostupKUnitu(string $unitId, string $accountId): bool;

    public function sozdatUnit(ParametriSozdaniyaUnita $parametri, string $accountId): string;

    /**
     * Групповое создание юнитов с автоматическими связями через хелперы.
     *
     * @param list<array{projectId: string, branch: string}> $projects
     *
     * @return list<SozdanniyUnit>
     */
    public function sozdatUnitiDlyaProektov(
        array $projects,
        string $unitName,
        string $accountId,
    ): array;

    public function prodvinutCiklUnita(string $unitId, string $accountId): void;
}
