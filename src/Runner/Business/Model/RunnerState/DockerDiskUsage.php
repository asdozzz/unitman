<?php

declare(strict_types=1);

namespace App\Runner\Business\Model\RunnerState;

/**
 * Занятое Docker место на хосте раннера (Docker Engine API `/system/df`).
 */
final class DockerDiskUsage implements \JsonSerializable
{
    public function __construct(
        public int $layersBytes = 0,
        public int $containersBytes = 0,
        public int $volumesBytes = 0,
        public int $buildCacheBytes = 0,
        public int $totalBytes = 0,
        public int $reclaimableBytes = 0,
    ) {
    }

    public function jsonSerialize(): mixed
    {
        return get_object_vars($this);
    }
}
