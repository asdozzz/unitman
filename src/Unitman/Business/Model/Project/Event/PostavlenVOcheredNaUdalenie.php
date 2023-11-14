<?php

namespace App\Unitman\Business\Model\Project\Event;

final class PostavlenVOcheredNaUdalenie
{
    public function __construct(
        public readonly string $id,
        public readonly string $jobId,
    )
    {
    }

}
