<?php

namespace App\Unitman\Business\Command\Unit;

use App\Unitman\Business\Command\Unit\SozdatUnit\VolumeZapros;
use App\Unitman\Business\Command\Unit\SozdatUnit\ZnacheniePeremenoi;
use App\Utils\Converter\JsonBodySerializableInterface;

final class SozdatUnit implements JsonBodySerializableInterface
{
    /**
     * @var ZnacheniePeremenoi[] $znacheniePeremenoi
     * */
    public readonly array $znacheniePeremenoi;

    /**
     * Тома юнита, выбранные клиентом по ответу `poluchitKonfigIzHranilisha`.
     * Файлы грузятся до создания юнита как черновики медиатеки.
     *
     * @var VolumeZapros[] $volumes
     */
    public readonly array $volumes;

    /**
     * @param ZnacheniePeremenoi[] $znacheniePeremenoi
     * @param VolumeZapros[] $volumes
     * */
    public function __construct(
        public readonly string $projectId,
        public readonly string $unitName,
        public readonly string $branch,
        array $znacheniePeremenoi = [],
        public readonly int $memoryLimit = 0,
        array $volumes = []
    )
    {
        $this->znacheniePeremenoi = $znacheniePeremenoi;
        $this->volumes = $volumes;
    }
}
