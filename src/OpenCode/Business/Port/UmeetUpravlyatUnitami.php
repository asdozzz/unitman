<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Port;

use App\OpenCode\Business\Model\ParametriSozdaniyaUnita;
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

    public function prodvinutCiklUnita(string $unitId, string $accountId): void;
}
