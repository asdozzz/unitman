<?php
declare(strict_types=1);

namespace App\Runner\Business\Model\GolangRunner\Project;

final class InitProjectResult
{
    /**
     * @param array<\App\Runner\Business\Model\GolangRunner\Unit\Step> $Steps
     * */
    public function __construct(public readonly bool $Success,public readonly array $Steps)
    {
    }

}
