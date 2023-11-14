<?php
declare(strict_types=1);

namespace App\Runner\Business\Model;

final class InitProjectResult
{
    public function __construct(public readonly bool $Success,public readonly string $Message)
    {
    }

}
