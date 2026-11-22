<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Workspace\Port;

use App\OpenCode\Business\Model\HodOpenCode;

interface WorkspaceTurnRepository
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
    public function findBySessionId(string $workspaceId): array;

    /**
     * @return list<HodOpenCode>
     */
    public function findVProzesse(int $limit): array;

    public function findPosledniiPoSessii(string $workspaceId): ?HodOpenCode;

    public function sleduyushiiSeq(string $workspaceId): int;
}
