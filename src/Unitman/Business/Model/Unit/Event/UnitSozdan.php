<?php

namespace App\Unitman\Business\Model\Unit\Event;

final class UnitSozdan
{
    /**
     * @param array<int, array<string, mixed>> $volumes тома юнита (VolumeValue::toArray())
     * @param array<int, array<string, mixed>> $svyazi связи переменных с выбранными юнитами
     *                                                (PeremennayaSvyaz::toArray())
     */
    public function __construct(
        public readonly string $id,
        public readonly string $authorId,
        public readonly string $projectId,
        public readonly string $name,
        public readonly string $branch,
        public readonly array $stateAsArray,
        public readonly array $values = [],
        public readonly int $memoryLimit = 3072,
        public readonly array $volumes = [],
        public readonly array $svyazi = []
    )
    {
    }

}
