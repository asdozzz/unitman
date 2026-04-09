<?php

namespace App\Unitman\Business\Command\Project;

final class PoluchitMoiProekti
{
    public function __construct(public readonly int $limit = 10, public readonly int $offset = 0)
    {
    }
}
