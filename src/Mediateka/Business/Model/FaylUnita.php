<?php

declare(strict_types=1);

namespace App\Mediateka\Business\Model;

use DomainException;

/**
 * Файл медиатеки. Источник истины — хранилище (MinIO),
 * эта модель — реестр метаданных.
 *
 * Файл может быть черновиком: он загружен до создания юнита и ещё не привязан
 * ни к одному юниту (`unitId === null`). Такой файл привязывается к юниту
 * отдельным шагом — при создании юнита либо явным вызовом привязки.
 */
final class FaylUnita
{
    public const MIME_TYPE_ZIP = 'application/zip';

    public function __construct(
        private readonly string $id,
        private readonly ?string $unitId,
        private readonly string $name,
        private readonly int $size,
        private readonly string $mimeType,
        private readonly string $storageKey,
        private readonly ?string $uploadedBy,
        private readonly int $createdAt
    ) {
        if (trim($this->id) === '') {
            throw new DomainException('mediateka.file.id_is_empty');
        }
        if ($this->unitId !== null && trim($this->unitId) === '') {
            throw new DomainException('mediateka.file.unit_id_is_empty');
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
            'name' => $this->name,
            'size' => $this->size,
            'mimeType' => $this->mimeType,
            'uploadedBy' => $this->uploadedBy,
            'createdAt' => $this->createdAt,
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        $unitId = $row['unit_id'] ?? null;

        return new self(
            (string) $row['id'],
            $unitId === null || $unitId === '' ? null : (string) $unitId,
            (string) $row['name'],
            (int) $row['size'],
            (string) $row['mime_type'],
            (string) $row['storage_key'],
            isset($row['uploaded_by']) ? (string) $row['uploaded_by'] : null,
            (int) $row['created_at']
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

        return new self(
            $this->id,
            $unitId,
            $this->name,
            $this->size,
            $this->mimeType,
            $this->storageKey,
            $this->uploadedBy,
            $this->createdAt
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

    /**
     * Черновик: файл загружен, но ещё не привязан к юниту.
     */
    public function esliChernovik(): bool
    {
        return $this->unitId === null;
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
