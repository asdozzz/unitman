<?php

namespace App\Unitman\Business\Model\Project\Event;

final class ProjectWasEnabled
{
    public function __construct(
        public readonly string $id
    )
    {
    }

}
