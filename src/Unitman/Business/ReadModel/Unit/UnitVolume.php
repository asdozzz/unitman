<?php

namespace App\Unitman\Business\ReadModel\Unit;

use App\Unitman\Business\Model\Unit\ConfigUnita\VolumeConfig;
use App\Unitman\Business\Model\Unit\VolumeValue;

/**
 * Том юнита в ответах API.
 *
 * Объединяет описание тома из конфига (`unitman_volumes`) и текущее
 * значение файла. `variable` — идентификатор переменной конфига, в которой
 * лежит ид файла медиатеки; `fileId` — привязанный файл (null, пока не задан).
 */
final class UnitVolume
{
    public function __construct(
        private readonly string $dir,
        private readonly string $name,
        private readonly ?string $variable,
        private readonly ?string $fileId,
    )
    {
    }

    /**
     * @param array<string, VolumeValue> $volumeValuesByDir текущие значения томов по dir
     * @param array<string, string> $valuesVariableMap значения переменных конфига по их id
     */
    public static function fromConfig(
        VolumeConfig $volumeConfig,
        array $volumeValuesByDir = [],
        array $valuesVariableMap = []
    ): self {
        $volumeValue = $volumeValuesByDir[$volumeConfig->getDir()] ?? null;
        $fileId = $volumeValue?->getFileId();

        // У только что созданного юнита томов ещё нет: `Unit::sozdatUnit()`
        // пишет только значения переменных. Ид файла в этом случае берётся
        // из переменной, указанной в `volume.variable`.
        if ($fileId === null) {
            $variable = $volumeConfig->getVariable();

            if ($variable !== null) {
                $value = $valuesVariableMap[$variable] ?? '';

                if (is_string($value) && trim($value) !== '') {
                    $fileId = trim($value);
                }
            }
        }

        return new self(
            $volumeConfig->getDir(),
            $volumeConfig->getName(),
            $volumeConfig->getVariable(),
            $fileId,
        );
    }

    public function toArray(): array
    {
        return [
            'dir' => $this->dir,
            'name' => $this->name,
            'variable' => $this->variable,
            'fileId' => $this->fileId,
        ];
    }

    public function getDir(): string
    {
        return $this->dir;
    }

    public function getVariable(): ?string
    {
        return $this->variable;
    }

    public function getFileId(): ?string
    {
        return $this->fileId;
    }
}
