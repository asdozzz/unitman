<?php

namespace App\Unitman\Business\Model\Project\Event;

final class ProjectWasDeletedManually
{
    public function __construct(
        public readonly string $id
    )
    {
    }
}
