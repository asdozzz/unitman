<?php

namespace App\Unitman\Business\Command\Project;

final class GetProjectList
{
    public function __construct(public readonly int $limit = 10, public readonly int $offset = 0)
    {
    }
}
