<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Port;

use App\OpenCode\Business\Model\HodOpenCode;

interface HodOpenCodeRepository
{
    public function save(HodOpenCode $turn): void;

    public function update(HodOpenCode $turn): void;

    /**
     * @throws \DomainException если ход не найден
     */
    public function getById(string $id): HodOpenCode;

    public function findById(string $id): ?HodOpenCode;

    public function findByWorkflowId(string $workflowId): ?HodOpenCode;

    /**
     * @return list<HodOpenCode>
     */
    public function findBySessionId(string $sessionId): array;

    /**
     * Последний ход сессии — именно он определяет, ждёт ли сессия ответа.
     */
    public function findPosledniiPoSessii(string $sessionId): ?HodOpenCode;

    public function sleduyushiiSeq(string $sessionId): int;
}