<?php

declare(strict_types=1);

namespace App\Unitman\Business\Model\VolumeType;

use DomainException;

/**
 * Связь типа тома проекта с архивом медиатеки.
 *
 * `projectId` денормализован: он же есть у типа и у файла, но хранится в связи,
 * чтобы проверять принадлежность архива проекту одним индексным запросом.
 */
final class VolumeTypeFile
{
    public function __construct(
        public readonly string $typeId,
        public readonly string $projectId,
        public readonly string $fileId,
        public readonly int $createdAt
    ) {
        if (trim($this->typeId) === '') {
            throw new DomainException('unit.volume_type_file.type_id_is_empty');
        }
        if (trim($this->projectId) === '') {
            throw new DomainException('unit.volume_type_file.project_id_is_empty');
        }
        if (trim($this->fileId) === '') {
            throw new DomainException('unit.volume_type_file.file_id_is_empty');
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            (string) $row['type_id'],
            (string) $row['project_id'],
            (string) $row['file_id'],
            (int) $row['created_at']
        );
    }
}
