<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Port;

use App\OpenCode\Business\Model\UchastnikSessii;

/**
 * Участники workspace-сессий.
 *
 * Порт не знает про роли доступа: он только хранит и отдаёт строки. Решения
 * «кто что может» принимает политика доступа, а «чей бюджет» — резолвер
 * реквизитов автора.
 */
interface UchastnikiSessiiRepository
{
    /**
     * @return list<UchastnikSessii>
     */
    public function findBySessionId(string $sessionId): array;

    /**
     * Батч-выборка для списков и уведомлений без N+1.
     *
     * @param list<string> $sessionIds
     *
     * @return array<string, list<UchastnikSessii>> ключ — идентификатор сессии
     */
    public function findBySessionIds(array $sessionIds): array;

    /**
     * @return list<UchastnikSessii>
     */
    public function findByAccountId(string $accountId): array;

    public function find(string $sessionId, string $accountId): ?UchastnikSessii;

    public function save(UchastnikSessii $uchastnik): void;

    public function udalit(UchastnikSessii $uchastnik): void;
}
