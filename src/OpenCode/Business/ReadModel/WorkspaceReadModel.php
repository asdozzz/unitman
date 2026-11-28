<?php

declare(strict_types=1);

namespace App\OpenCode\Business\ReadModel;

use App\OpenCode\Business\Model\ProektWorkspace;
use App\OpenCode\Business\Model\SessiyaOpenCode;
use App\OpenCode\Business\Model\WorkspaceUnit;

/**
 * Workspace в списке и деталях.
 *
 * `status` — статус жизненного цикла (`Workspace`), `archived` — логическая
 * архивация сессии (`archived_at`). Оба поля отдаются отдельно: архивный
 * workspace сначала проходит `ARCHIVING`, а признак архива появляется только
 * после успешной остановки контейнера.
 */
final class WorkspaceReadModel
{
    /**
     * @param list<ProektWorkspaceReadModel> $projects
     * @param list<WorkspaceUnitReadModel> $units
     * @param list<WorkspaceFileReadModel> $files
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
        public readonly int $createdAt,
        public readonly int $updatedAt,
        public readonly array $units = [],
        public readonly ?string $model = null,
        public readonly array $files = [],
        public readonly bool $updating = false,
        public readonly string $myRole = 'owner',
        public readonly string $ownerAccountId = '',
        public readonly int $participantsCount = 1,
        public readonly bool $shared = false,
    ) {
    }

    /**
     * @param list<WorkspaceFileReadModel> $files
     */
    public static function izModela(
        SessiyaOpenCode $session,
        ?string $ideLink = null,
        array $files = [],
        bool $updating = false,
        string $myRole = 'owner',
        int $participantsCount = 1,
        bool $shared = false,
    ): self {
        $workspace = $session->getWorkspace();

        return new self(
            $session->getId(),
            $session->getTitle(),
            $workspace?->getBranch() ?? '',
            $workspace?->getStatus()->value ?? '',
            $workspace?->isReadOnly() ?? false,
            $session->estArhiv(),
            $session->getArchiveReason(),
            $workspace?->getErrorKey(),
            array_map(
                static fn (ProektWorkspace $project): ProektWorkspaceReadModel => ProektWorkspaceReadModel::izModela($project),
                $workspace?->getProjects() ?? [],
            ),
            $ideLink,
            $session->getCreatedAt(),
            $session->getUpdatedAt(),
            array_map(
                static fn (WorkspaceUnit $unit): WorkspaceUnitReadModel => WorkspaceUnitReadModel::izModela($unit),
                $workspace?->getUnits() ?? [],
            ),
            $session->getModel(),
            $files,
            $updating,
            $myRole,
            $session->getAccountId(),
            $participantsCount,
            $shared,
        );
    }

    /**
     * @return array{
     *     id: string,
     *     name: string,
     *     branch: string,
     *     status: string,
     *     readOnly: bool,
     *     archived: bool,
     *     archiveReason: string|null,
     *     errorKey: string|null,
     *     projects: list<array<string, mixed>>,
     *     ideLink: string|null,
     *     createdAt: int,
     *     updatedAt: int,
     *     units: list<array<string, mixed>>,
     *     model: string|null,
     *     files: list<array<string, mixed>>,
     *     updating: bool,
     *     myRole: string,
     *     ownerAccountId: string,
     *     participantsCount: int,
     *     shared: bool
     * }
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
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
            'units' => array_map(
                static fn (WorkspaceUnitReadModel $unit): array => $unit->toArray(),
                $this->units,
            ),
            'model' => $this->model,
            'files' => array_map(
                static fn (WorkspaceFileReadModel $file): array => $file->toArray(),
                $this->files,
            ),
            'updating' => $this->updating,
            'myRole' => $this->myRole,
            'ownerAccountId' => $this->ownerAccountId,
            'participantsCount' => $this->participantsCount,
            'shared' => $this->shared,
        ];
    }
}
