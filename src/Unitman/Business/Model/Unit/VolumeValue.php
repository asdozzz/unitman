<?php

declare(strict_types=1);

namespace App\Unitman\Business\Model\Unit;

/**
 * Значение тома юнита: описание из блока конфига `unitman_volumes`
 * + идентификатор файла из медиатеки, если он задан.
 */
final class VolumeValue
{
    private string $dir;
    private string $name;
    private ?string $fileId;

    public function __construct(
        string $dir,
        string $name,
        ?string $fileId = null
    )
    {
        $dir = trim($dir);
        $name = trim($name);

        if ($dir === '') {
            throw new \DomainException('unit.volume_value.dir_is_empty');
        }

        if ($name === '') {
            throw new \DomainException('unit.volume_value.name_is_empty');
        }

        if ($fileId !== null && trim($fileId) === '') {
            throw new \DomainException('unit.volume_value.file_id_is_empty');
        }

        $this->dir = $dir;
        $this->name = $name;
        $this->fileId = $fileId === null ? null : trim($fileId);
    }

    function toArray(): array
    {
        return [
            'dir' => $this->dir,
            'name' => $this->name,
            'fileId' => $this->fileId,
        ];
    }

    public static function fromArray(array $data): self
    {
        if (!isset($data['dir']) || !is_string($data['dir'])) {
            throw new \DomainException('unit.volume_value.dir_is_empty');
        }

        if (!isset($data['name']) || !is_string($data['name'])) {
            throw new \DomainException('unit.volume_value.name_is_empty');
        }

        $fileId = $data['fileId'] ?? null;

        if ($fileId !== null && !is_string($fileId)) {
            throw new \DomainException('unit.volume_value.file_id_is_empty');
        }

        return new self($data['dir'], $data['name'], $fileId);
    }

    public function getDir(): string
    {
        return $this->dir;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getFileId(): ?string
    {
        return $this->fileId;
    }

    public function esliSFaylom(): bool
    {
        return $this->fileId !== null;
    }
}
