<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Model;

/**
 * Состояние workspace внутри сессии OpenCode.
 *
 * Workspace — value object: он не знает о юнитах и не хранит секретов,
 * только снимок проектов и параметры операции жизненного цикла.
 */
final class Workspace
{
    public const OPERATION_CREATE = 'create';
    public const OPERATION_START = 'start';
    public const OPERATION_STOP = 'stop';
    public const OPERATION_DELETE = 'delete';
    public const OPERATION_UPDATE = 'update';

    /** Максимум проектов в одном workspace. */
    public const MAX_PROJECTS = 20;

    private const BRANCH_MAX_LENGTH = 256;

    /**
     * @param list<ProektWorkspace> $projects
     * @param list<WorkspaceUnit> $units
     */
    private function __construct(
        private string $branch,
        private string $initialPrompt,
        private array $projects,
        private StatusWorkspace $status,
        private bool $readOnly,
        private ?string $errorKey,
        private string $operation,
        private string $operationWorkflowId,
        private int $operationAttempts,
        private int $nextAttemptAt,
        private ?int $initialPromptStartedAt,
        private int $createdAt,
        private int $updatedAt,
        private array $units = [],
    ) {
    }

    /**
     * @param list<ProektWorkspace> $projects
     */
    public static function create(
        string $branch,
        string $initialPrompt,
        array $projects,
        int $now,
    ): self {
        $branch = self::normalizeBranch($branch);
        $initialPrompt = self::normalizePrompt($initialPrompt);
        $projects = self::normalizeProjects($projects, $branch);

        return new self(
            $branch,
            $initialPrompt,
            $projects,
            StatusWorkspace::CREATING,
            self::readOnlyDlyaProektov($projects),
            null,
            self::OPERATION_CREATE,
            '',
            0,
            0,
            null,
            $now,
            $now,
        );
    }

    /**
     * @param list<ProektWorkspace> $projects
     * @param list<WorkspaceUnit> $units
     */
    public static function restore(
        string $branch,
        string $initialPrompt,
        array $projects,
        StatusWorkspace $status,
        bool $readOnly,
        ?string $errorKey,
        string $operation,
        string $operationWorkflowId,
        int $operationAttempts,
        int $nextAttemptAt,
        ?int $initialPromptStartedAt,
        int $createdAt,
        int $updatedAt,
        array $units = [],
    ): self {
        $branch = self::normalizeBranch($branch);

        return new self(
            $branch,
            self::normalizePrompt($initialPrompt),
            self::normalizeProjects($projects, $branch),
            $status,
            $readOnly,
            $errorKey,
            $operation,
            $operationWorkflowId,
            $operationAttempts,
            $nextAttemptAt,
            $initialPromptStartedAt,
            $createdAt,
            $updatedAt,
            self::normalizeUnits($units),
        );
    }

    public function getBranch(): string
    {
        return $this->branch;
    }

    public function getInitialPrompt(): string
    {
        return $this->initialPrompt;
    }

    /**
     * @return list<ProektWorkspace>
     */
    public function getProjects(): array
    {
        return $this->projects;
    }

    /**
     * @return list<WorkspaceUnit>
     */
    public function getUnits(): array
    {
        return $this->units;
    }

    /**
     * Фиксирует юниты, созданные из workspace.
     *
     * @param list<WorkspaceUnit> $units
     */
    public function zapomnitUniti(array $units, int $now): void
    {
        $this->units = self::normalizeUnits($units);
        $this->updatedAt = $now;
    }

    public function getStatus(): StatusWorkspace
    {
        return $this->status;
    }

    public function isReadOnly(): bool
    {
        return $this->readOnly;
    }

    public function getErrorKey(): ?string
    {
        return $this->errorKey;
    }

    public function getOperation(): string
    {
        return $this->operation;
    }

    public function getOperationWorkflowId(): string
    {
        return $this->operationWorkflowId;
    }

    public function getOperationAttempts(): int
    {
        return $this->operationAttempts;
    }

    public function getNextAttemptAt(): int
    {
        return $this->nextAttemptAt;
    }

    public function getInitialPromptStartedAt(): ?int
    {
        return $this->initialPromptStartedAt;
    }

    public function getCreatedAt(): int
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): int
    {
        return $this->updatedAt;
    }

    /**
     * @throws \DomainException если переход недопустим
     */
    public function perevestiV(StatusWorkspace $status, int $now): void
    {
        if (!$this->status->mozhnoPerevestiV($status)) {
            throw new \DomainException('opencode.workspace.invalid_transition');
        }

        $this->status = $status;
        $this->updatedAt = $now;
    }

    public function zapomnitOperaciyu(string $operation, string $workflowId, int $now): void
    {
        $this->operation = $operation;
        $this->operationWorkflowId = $workflowId;
        $this->updatedAt = $now;
    }

    public function sbrositOperaciyu(int $now): void
    {
        $this->operation = '';
        $this->operationWorkflowId = '';
        $this->operationAttempts = 0;
        $this->nextAttemptAt = 0;
        $this->updatedAt = $now;
    }

    /**
     * Добавляет проекты в уже существующий workspace.
     *
     * Позиции пересчитываются от текущего количества. Если хотя бы один
     * новый проект не имеет права push, workspace становится read-only.
     *
     * @param list<ProektWorkspace> $projects
     */
    public function dobavitProekty(array $projects, int $now): void
    {
        if ($projects === []) {
            return;
        }

        $offset = count($this->projects);
        $dopolnenie = [];

        foreach ($projects as $project) {
            if (!$project instanceof ProektWorkspace) {
                throw new \DomainException('opencode.workspace.project_invalid');
            }

            $dopolnenie[] = new ProektWorkspace(
                $project->projectId,
                $project->projectName,
                $project->projectCode,
                $project->canPush,
                $offset + count($dopolnenie),
                $project->mainBranch,
                $project->branch,
            );
        }

        $this->projects = self::normalizeProjects(
            array_merge($this->projects, $dopolnenie),
            $this->branch,
        );

        if (self::readOnlyDlyaProektov($dopolnenie)) {
            $this->readOnly = true;
        }

        $this->updatedAt = $now;
    }

    public function otmetitOshibku(string $errorKey, int $now): void
    {
        $this->errorKey = $errorKey;

        if ($this->status->mozhnoPerevestiV(StatusWorkspace::ERROR)) {
            $this->status = StatusWorkspace::ERROR;
        }

        $this->updatedAt = $now;
    }

    public function ochistitOshibku(int $now): void
    {
        $this->errorKey = null;
        $this->updatedAt = $now;
    }

    public function zapomnitPopitku(int $attempts, int $nextAttemptAt, int $now): void
    {
        $this->operationAttempts = $attempts;
        $this->nextAttemptAt = $nextAttemptAt;
        $this->updatedAt = $now;
    }

    public function otmetitStartPervogoPrompta(int $now): void
    {
        $this->initialPromptStartedAt = $now;
        $this->updatedAt = $now;
    }

    private static function normalizeBranch(string $branch): string
    {
        $branch = trim($branch);

        if ($branch === '') {
            throw new \DomainException('opencode.workspace.branch_required');
        }

        if (mb_strlen($branch) > self::BRANCH_MAX_LENGTH) {
            throw new \DomainException('opencode.workspace.branch_too_long');
        }

        return $branch;
    }

    private static function normalizePrompt(string $initialPrompt): string
    {
        $initialPrompt = trim($initialPrompt);

        if ($initialPrompt === '') {
            throw new \DomainException('opencode.workspace.prompt_required');
        }

        return $initialPrompt;
    }

    /**
     * @param list<ProektWorkspace> $projects
     *
     * @return list<ProektWorkspace>
     */
    private static function normalizeProjects(array $projects, string $workspaceBranch = ''): array
    {
        if ($projects === []) {
            throw new \DomainException('opencode.workspace.projects_required');
        }

        $unique = [];
        $seen = [];

        foreach ($projects as $project) {
            if (isset($seen[$project->projectId])) {
                throw new \DomainException('opencode.workspace.project_conflict');
            }

            $seen[$project->projectId] = true;

            // Старые снимки не содержат ветку проекта: фактической веткой
            // становится общая ветка workspace.
            if ($project->branch === '' && $workspaceBranch !== '') {
                $project = $project->sVetkoy($workspaceBranch);
            }

            $unique[] = $project;
        }

        return $unique;
    }

    /**
     * @param list<WorkspaceUnit> $units
     *
     * @return list<WorkspaceUnit>
     */
    private static function normalizeUnits(array $units): array
    {
        $result = [];
        $seen = [];

        foreach ($units as $unit) {
            if (!$unit instanceof WorkspaceUnit) {
                throw new \DomainException('opencode.workspace.unit_invalid');
            }

            if ($unit->unitId === '' || $unit->projectId === '') {
                throw new \DomainException('opencode.workspace.unit_invalid');
            }

            if (isset($seen[$unit->unitId])) {
                throw new \DomainException('opencode.workspace.unit_conflict');
            }

            $seen[$unit->unitId] = true;
            $result[] = $unit;
        }

        return $result;
    }

    /**
     * @param list<ProektWorkspace> $projects
     */
    private static function readOnlyDlyaProektov(array $projects): bool
    {
        foreach ($projects as $project) {
            if (!$project->canPush) {
                return true;
            }
        }

        return false;
    }
}
