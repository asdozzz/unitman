<?php

declare(strict_types=1);

namespace App\Unitman\Business\ReadModel\VolumeType;

/**
 * Файл каталога типа тома в ответе `POST /api/volumeType/list`.
 */
final class VolumeTypeFileItem
{
    public function __construct(
        public readonly string $fileId,
        public readonly string $name,
        public readonly int $size,
        public readonly int $createdAt
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'fileId' => $this->fileId,
            'name' => $this->name,
            'size' => $this->size,
            'createdAt' => $this->createdAt,
        ];
    }
}
