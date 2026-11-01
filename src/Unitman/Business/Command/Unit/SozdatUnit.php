<?php

namespace App\Unitman\Business\Command\Unit;

use App\Unitman\Business\Command\Unit\SozdatUnit\ZnacheniePeremenoi;
use App\Utils\Converter\JsonBodySerializableInterface;

final class SozdatUnit implements JsonBodySerializableInterface
{
    /**
     * @var ZnacheniePeremenoi[] $znacheniePeremenoi
     * */
    public readonly array $znacheniePeremenoi;

    /**
     * Идентификаторы файлов, загруженных до создания юнита (черновики медиатеки).
     *
     * @var string[] $fileIds
     */
    public readonly array $fileIds;

    /**
     * @param ZnacheniePeremenoi[] $znacheniePeremenoi
     * @param string[] $fileIds
     * */
    public function __construct(
        public readonly string $projectId,
        public readonly string $unitName,
        public readonly string $branch,
        array $znacheniePeremenoi = [],
        public readonly int $memoryLimit = 0,
        array $fileIds = []
    )
    {
        $this->znacheniePeremenoi = $znacheniePeremenoi;
        $this->fileIds = $fileIds;
    }
}
