<?php

namespace App\Unitman\Business\Model\Project\Event;

final class ProjectWasDisabled
{
    public function __construct(
        public readonly string $id
    )
    {
    }

}
