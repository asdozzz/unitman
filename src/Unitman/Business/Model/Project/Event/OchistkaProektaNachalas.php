<?php

namespace App\Unitman\Business\Model\Project\Event;

final class OchistkaProektaNachalas
{
    public function __construct(
        public readonly string $id,
        public readonly string $jobId
    )
    {
    }

}
