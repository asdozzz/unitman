<?php

declare(strict_types=1);

namespace App\OpenCode\Business\UnitSession\Port;

use App\OpenCode\Business\UnitSession\Model\UnitSession;
use App\OpenCode\Business\UnitSession\Model\UnitSessionFilter;

interface UnitSessionRepository
{
    public function save(UnitSession $session): void;

    public function update(UnitSession $session): void;

    /**
     * @throws \DomainException если сессия не найдена
     */
    public function getById(string $id): UnitSession;

    public function findById(string $id): ?UnitSession;

    /**
     * Активная сессия юнита. У юнита не может быть двух активных сессий.
     */
    public function findAktivnayaPoUnitu(string $unitId): ?UnitSession;

    /**
     * @return list<UnitSession>
     */
    public function findByAccountId(string $accountId, UnitSessionFilter $filter): array;

    /**
     * Сессии, привязанные к указанным юнитам (для доступа редакторов).
     *
     * @param list<string> $unitIds
     * @return list<UnitSession>
     */
    public function findByUnitIds(array $unitIds, UnitSessionFilter $filter): array;

    /**
     * @return list<UnitSession>
     */
    public function findAktivnie(int $limit): array;
}
