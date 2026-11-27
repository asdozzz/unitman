<?php

declare(strict_types=1);

namespace App\Mediateka\Business\Model;

use DomainException;

/**
 * Файл медиатеки. Источник истины — хранилище (MinIO),
 * эта модель — реестр метаданных.
 *
 * Владелец файла не более чем один:
 * - черновик: все идентификаторы владельцев null — загружен до создания
 *   сущности и привязывается отдельным шагом;
 * - файл юнита: `unitId !== null` — удаляется вместе с юнитом;
 * - архив проекта: `projectId !== null` — общий архив каталога типов томов;
 * - файл workspace: `workspaceId !== null` — лежит в `/workspace/unitman_files`
 *   и доступен агенту только для чтения.
 */
final class FaylUnita
{
    public const MIME_TYPE_ZIP = 'application/zip';

    public const KIND_UNIT = 'unit';
    public const KIND_PROJECT = 'project';
    public const KIND_WORKSPACE = 'workspace';
    public const KIND_DRAFT = 'draft';

    public function __construct(
        private readonly string $id,
        private readonly ?string $unitId,
        private readonly string $name,
        private readonly int $size,
        private readonly string $mimeType,
        private readonly string $storageKey,
        private readonly ?string $uploadedBy,
        private readonly int $createdAt,
        private readonly ?string $projectId = null,
        private readonly ?string $workspaceId = null,
        private readonly string $kind = '',
        private readonly ?int $appliedAt = null,
        private readonly ?string $opencodeTurnId = null,
    ) {
        if (trim($this->id) === '') {
            throw new DomainException('mediateka.file.id_is_empty');
        }
        if ($this->unitId !== null && trim($this->unitId) === '') {
            throw new DomainException('mediateka.file.unit_id_is_empty');
        }
        if ($this->projectId !== null && trim($this->projectId) === '') {
            throw new DomainException('mediateka.file.project_id_is_empty');
        }
        if ($this->workspaceId !== null && trim($this->workspaceId) === '') {
            throw new DomainException('mediateka.file.workspace_id_is_empty');
        }
        if ($this->opencodeTurnId !== null && trim($this->opencodeTurnId) === '') {
            throw new DomainException('mediateka.file.opencode_turn_id_is_empty');
        }
        if ($this->opencodeTurnId !== null && $this->workspaceId === null) {
            throw new DomainException('mediateka.file.turn_without_workspace');
        }

        $owners = array_filter(
            [$this->unitId, $this->projectId, $this->workspaceId],
            static fn (?string $owner): bool => $owner !== null,
        );

        if (count($owners) > 1) {
            throw new DomainException('mediateka.file.owner_is_ambiguous');
        }

        if (trim($this->storageKey) === '') {
            throw new DomainException('mediateka.file.storage_key_is_empty');
        }
        if ($this->size < 0) {
            throw new DomainException('mediateka.file.size_is_negative');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'unitId' => $this->unitId,
            'projectId' => $this->projectId,
            'workspaceId' => $this->workspaceId,
            'kind' => $this->getKind(),
            'name' => $this->name,
            'size' => $this->size,
            'mimeType' => $this->mimeType,
            'uploadedBy' => $this->uploadedBy,
            'appliedAt' => $this->appliedAt,
            'opencodeTurnId' => $this->opencodeTurnId,
            'createdAt' => $this->createdAt,
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        $unitId = $row['unit_id'] ?? null;
        $projectId = $row['project_id'] ?? null;
        $workspaceId = $row['workspace_id'] ?? null;
        $appliedAt = $row['applied_at'] ?? null;
        $opencodeTurnId = $row['opencode_turn_id'] ?? null;

        return new self(
            (string) $row['id'],
            $unitId === null || $unitId === '' ? null : (string) $unitId,
            (string) $row['name'],
            (int) $row['size'],
            (string) $row['mime_type'],
            (string) $row['storage_key'],
            isset($row['uploaded_by']) ? (string) $row['uploaded_by'] : null,
            (int) $row['created_at'],
            $projectId === null || $projectId === '' ? null : (string) $projectId,
            $workspaceId === null || $workspaceId === '' ? null : (string) $workspaceId,
            isset($row['kind']) && is_string($row['kind']) ? $row['kind'] : '',
            $appliedAt === null || $appliedAt === '' ? null : (int) $appliedAt,
            $opencodeTurnId === null || $opencodeTurnId === '' ? null : (string) $opencodeTurnId,
        );
    }

    /**
     * Копия файла, привязанная к юниту. Ключ в хранилище не меняется:
     * объект уже лежит по своему ключу, привязка — это только запись в реестре.
     */
    public function prikrepitKUnitu(string $unitId): self
    {
        if (trim($unitId) === '') {
            throw new DomainException('mediateka.file.unit_id_is_empty');
        }

        if ($this->projectId !== null || $this->workspaceId !== null) {
            throw new DomainException('mediateka.file.is_not_unit_draft');
        }

        return new self(
            $this->id,
            $unitId,
            $this->name,
            $this->size,
            $this->mimeType,
            $this->storageKey,
            $this->uploadedBy,
            $this->createdAt,
            null,
            null,
            self::KIND_UNIT,
            null,
            null,
        );
    }

    /**
     * Копия файла, привязанная к workspace. Применение к контейнеру
     * фиксируется отдельно: `appliedAt` остаётся null до успешной операции.
     */
    public function prikrepitKWorkspace(string $workspaceId): self
    {
        if (trim($workspaceId) === '') {
            throw new DomainException('mediateka.file.workspace_id_is_empty');
        }

        if ($this->unitId !== null || $this->projectId !== null) {
            throw new DomainException('mediateka.file.is_not_workspace_draft');
        }

        return new self(
            $this->id,
            null,
            $this->name,
            $this->size,
            $this->mimeType,
            $this->storageKey,
            $this->uploadedBy,
            $this->createdAt,
            null,
            $workspaceId,
            self::KIND_WORKSPACE,
            $this->appliedAt,
            $this->opencodeTurnId,
        );
    }

    /**
     * Копия черновика, привязанная к ходу OpenCode как файл хода.
     *
     * Файл остаётся владельцем workspace: `workspaceId` задаётся
     * идентификатором workspace, `opencodeTurnId` — идентификатором хода.
     * `appliedAt` фиксируется моментом привязки.
     */
    public function prikrepitKWorkspaceKakFajlHoda(string $workspaceId, string $turnId, int $now): self
    {
        if (trim($workspaceId) === '') {
            throw new DomainException('mediateka.file.workspace_id_is_empty');
        }
        if (trim($turnId) === '') {
            throw new DomainException('mediateka.file.opencode_turn_id_is_empty');
        }

        if ($this->unitId !== null || $this->projectId !== null) {
            throw new DomainException('mediateka.file.is_not_workspace_draft');
        }

        return new self(
            $this->id,
            null,
            $this->name,
            $this->size,
            $this->mimeType,
            $this->storageKey,
            $this->uploadedBy,
            $this->createdAt,
            null,
            $workspaceId,
            self::KIND_WORKSPACE,
            $now,
            $turnId,
        );
    }

    /**
     * Копия с отметкой о применении к контейнеру workspace.
     */
    public function primenen(int $appliedAt): self
    {
        if (!$this->esliFaylWorkspace()) {
            throw new DomainException('mediateka.file.is_not_workspace_file');
        }

        return new self(
            $this->id,
            null,
            $this->name,
            $this->size,
            $this->mimeType,
            $this->storageKey,
            $this->uploadedBy,
            $this->createdAt,
            null,
            $this->workspaceId,
            self::KIND_WORKSPACE,
            $appliedAt,
            $this->opencodeTurnId,
        );
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getUnitId(): ?string
    {
        return $this->unitId;
    }

    public function getProjectId(): ?string
    {
        return $this->projectId;
    }

    public function getWorkspaceId(): ?string
    {
        return $this->workspaceId;
    }

    public function getKind(): string
    {
        if ($this->kind !== '') {
            return $this->kind;
        }

        if ($this->unitId !== null) {
            return self::KIND_UNIT;
        }

        if ($this->projectId !== null) {
            return self::KIND_PROJECT;
        }

        if ($this->workspaceId !== null) {
            return self::KIND_WORKSPACE;
        }

        return self::KIND_DRAFT;
    }

    public function getAppliedAt(): ?int
    {
        return $this->appliedAt;
    }

    public function getOpencodeTurnId(): ?string
    {
        return $this->opencodeTurnId;
    }

    public function isPrimenen(): bool
    {
        return $this->appliedAt !== null;
    }

    /**
     * Черновик: файл загружен, но ещё не привязан ни к одной сущности.
     */
    public function esliChernovik(): bool
    {
        return $this->unitId === null
            && $this->projectId === null
            && $this->workspaceId === null;
    }

    /**
     * Архив каталога типов томов — принадлежит проекту, а не юниту.
     */
    public function esliFaylProekta(): bool
    {
        return $this->projectId !== null;
    }

    /**
     * Файл прикреплён к workspace (не обязательно уже применён).
     */
    public function esliFaylWorkspace(): bool
    {
        return $this->workspaceId !== null;
    }

    /**
     * Файл привязан к ходу OpenCode.
     */
    public function esliFajlHoda(): bool
    {
        return $this->opencodeTurnId !== null;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getStorageKey(): string
    {
        return $this->storageKey;
    }

    public function getUploadedBy(): ?string
    {
        return $this->uploadedBy;
    }

    public function getCreatedAt(): int
    {
        return $this->createdAt;
    }
}
