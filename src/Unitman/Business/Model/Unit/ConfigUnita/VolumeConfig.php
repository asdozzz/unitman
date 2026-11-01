<?php

declare(strict_types=1);

namespace App\Unitman\Business\Model\Unit\ConfigUnita;

/**
 * Описание тома из необязательного блока конфига юнита `unitman_volumes`.
 *
 * `dir` — уникальный ключ тома внутри конфига.
 */
final class VolumeConfig
{
    private string $dir;
    private string $name;

    public function __construct(
        string $dir,
        string $name
    )
    {
        $dir = trim($dir);
        $name = trim($name);

        if ($dir === '') {
            throw new \DomainException('unit.config.volumes.dir_is_empty');
        }

        if ($name === '') {
            throw new \DomainException('unit.config.volumes.name_is_empty');
        }

        $this->dir = $dir;
        $this->name = $name;
    }

    public static function fromArray(array $volumeData): self
    {
        return new self(
            (string) ($volumeData['dir'] ?? ''),
            (string) ($volumeData['name'] ?? '')
        );
    }

    function toArray(): array
    {
        return [
            'dir' => $this->dir,
            'name' => $this->name,
        ];
    }

    public function getDir(): string
    {
        return $this->dir;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
