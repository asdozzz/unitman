<?php

namespace App\Unitman\Business\Model\Project\Event;

final class ProektPostavlenVOcheredNaUdalenie
{
    public function __construct(
        public readonly string $id,
        public readonly string $jobId,
    )
    {
    }

}
