<?php

declare(strict_types=1);

namespace App\OpenCode\Business\ReadModel;

/**
 * Состояние workspace для интерфейса.
 *
 * `canAcceptMessages` — status RUNNING, сессия не в архиве, нет обновления и
 * последний ход не выполняется; дополнительно роль должна позволять писать
 * (owner или writer). Это то же правило, что и у гейта `PropuskHodaOpenCode`,
 * плюс ролевая проверка.
 */
final class SostoyanieWorkspaceReadModel
{
    /**
     * @param list<ProektWorkspaceReadModel> $projects
     * @param list<WorkspaceUnitReadModel> $units
     * @param list<WorkspaceFileReadModel> $files
     * @param list<UchastnikSessiiReadModel> $participants
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $branch,
        public readonly string $status,
        public readonly bool $readOnly,
        public readonly bool $archived,
        public readonly ?string $archiveReason,
        public readonly ?string $errorKey,
        public readonly array $projects,
        public readonly ?string $ideLink,
        public readonly bool $canAcceptMessages,
        public readonly ?string $lastTurnId,
        public readonly ?string $lastTurnStatus,
        public readonly array $units = [],
        public readonly array $files = [],
        public readonly bool $updating = false,
        public readonly string $myRole = 'viewer',
        public readonly bool $canManage = false,
        public readonly array $participants = [],
        public readonly ?string $myDefaultCredentialId = null,
        public readonly ?string $myDefaultModel = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'branch' => $this->branch,
            'status' => $this->status,
            'readOnly' => $this->readOnly,
            'archived' => $this->archived,
            'archiveReason' => $this->archiveReason,
            'errorKey' => $this->errorKey,
            'projects' => array_map(
                static fn (ProektWorkspaceReadModel $project): array => $project->toArray(),
                $this->projects,
            ),
            'ideLink' => $this->ideLink,
            'canAcceptMessages' => $this->canAcceptMessages,
            'lastTurnId' => $this->lastTurnId,
            'lastTurnStatus' => $this->lastTurnStatus,
            'units' => array_map(
                static fn (WorkspaceUnitReadModel $unit): array => $unit->toArray(),
                $this->units,
            ),
            'files' => array_map(
                static fn (WorkspaceFileReadModel $file): array => $file->toArray(),
                $this->files,
            ),
            'updating' => $this->updating,
            'myRole' => $this->myRole,
            'canManage' => $this->canManage,
            'participants' => array_map(
                static fn (UchastnikSessiiReadModel $uchastnik): array => $uchastnik->vMassiv(),
                $this->participants,
            ),
            'myDefaultCredentialId' => $this->myDefaultCredentialId,
            'myDefaultModel' => $this->myDefaultModel,
        ];
    }
}
