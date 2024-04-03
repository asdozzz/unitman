<?php
declare(strict_types=1);

namespace App\Runner\Business\Model;

final class RemoveProjectResult
{
    /**
     * @param array<Step> $Steps
     * */
    public function __construct(public readonly bool $Success,public readonly array $Steps)
    {
    }

}
