<?php

declare(strict_types=1);

namespace App\Unitman\Business\ReadModel\VolumeType;

/**
 * Элемент ответа `POST /api/volumeType/list`.
 */
final class VolumeTypeList
{
    /**
     * @param VolumeTypeFileItem[] $files
     */
    public function __construct(
        public readonly string $code,
        public readonly string $name,
        public readonly array $files
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'files' => array_map(static fn (VolumeTypeFileItem $file): array => $file->toArray(), $this->files),
        ];
    }
}
