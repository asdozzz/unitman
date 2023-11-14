<?php

namespace App\Runner\Business\Command;

final class RemoveProjectCommand
{
    public function __construct(public readonly string $ProjectId)
    {

    }
 }
