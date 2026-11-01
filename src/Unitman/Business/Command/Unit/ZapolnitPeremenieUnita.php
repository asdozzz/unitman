<?php

namespace App\Unitman\Business\Command\Unit;

use App\Unitman\Business\Command\Unit\ZapolnitPeremenieUnita\VolumeZapros;
use App\Utils\Converter\JsonBodySerializableInterface;

final class ZapolnitPeremenieUnita implements JsonBodySerializableInterface
{
    /**
     * @param array<array-key, mixed> $values
     * @param VolumeZapros[] $volumes Тома юнита, которые нужно заполнить/перезаполнить.
     *                               `dir` обязан присутствовать в `unitman_volumes` конфига юнита.
     */
    public function __construct(
        public readonly string $id,
        public readonly array $values = [],
        public readonly array $volumes = [],
    ) {
    }
}
