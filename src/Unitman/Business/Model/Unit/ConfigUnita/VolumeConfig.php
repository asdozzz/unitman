<?php

declare(strict_types=1);

namespace App\Unitman\Business\Model\Unit\ConfigUnita;

/**
 * Описание тома из необязательного блока конфига юнита `unitman_volumes`.
 */
final class VolumeConfig
{
    private string $dir;
    private string $name;
    private ?string $variable;

    public function __construct(
        string $dir,
        string $name,
        ?string $variable = null
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

        if ($variable !== null && trim($variable) === '') {
            throw new \DomainException('unit.config.volumes.variable_is_empty');
        }

        $this->dir = $dir;
        $this->name = $name;
        $this->variable = $variable === null ? null : trim($variable);
    }

    public static function fromArray(array $volumeData): self
    {
        return new self(
            (string) ($volumeData['dir'] ?? ''),
            (string) ($volumeData['name'] ?? ''),
            isset($volumeData['variable']) && is_string($volumeData['variable']) ? $volumeData['variable'] : null
        );
    }

    function toArray(): array
    {
        $result = [
            'dir' => $this->dir,
            'name' => $this->name,
        ];

        if ($this->variable !== null) {
            $result['variable'] = $this->variable;
        }

        return $result;
    }

    public function getDir(): string
    {
        return $this->dir;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getVariable(): ?string
    {
        return $this->variable;
    }
}
