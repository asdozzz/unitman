<?php

declare(strict_types=1);

namespace App\Skill\Business\Command;

final class PoluchitEffektivnieNavyki
{
    /**
     * @param list<string> $projectIds
     */
    public function __construct(public readonly array $projectIds = [])
    {
    }
}
