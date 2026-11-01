<?php

namespace App\Unitman\Business\ReadModel\Unit;

use App\Unitman\Business\Model\Unit\ConfigUnita\VolumeConfig;
use App\Unitman\Business\Model\Unit\VolumeValue;

/**
 * Том юнита в ответах API.
 *
 * Объединяет описание тома из конфига (`unitman_volumes`) и текущее
 * значение файла. `dir` — уникальный ключ тома; `fileId` — привязанный
 * файл медиатеки (null, пока не задан).
 */
final class UnitVolume
{
    public function __construct(
        private readonly string $dir,
        private readonly string $name,
        private readonly ?string $fileId,
    )
    {
    }

    /**
     * @param array<string, VolumeValue> $volumeValuesByDir текущие значения томов по dir
     */
    public static function fromConfig(
        VolumeConfig $volumeConfig,
        array $volumeValuesByDir = []
    ): self {
        $volumeValue = $volumeValuesByDir[$volumeConfig->getDir()] ?? null;

        return new self(
            $volumeConfig->getDir(),
            $volumeConfig->getName(),
            $volumeValue?->getFileId(),
        );
    }

    public function toArray(): array
    {
        return [
            'dir' => $this->dir,
            'name' => $this->name,
            'fileId' => $this->fileId,
        ];
    }

    public function getDir(): string
    {
        return $this->dir;
    }

    public function getFileId(): ?string
    {
        return $this->fileId;
    }
}
