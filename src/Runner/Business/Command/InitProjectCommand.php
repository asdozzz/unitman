<?php

namespace App\Runner\Business\Command;

final class InitProjectCommand
{
    public function __construct(public readonly string $ProjectId, public readonly string $MainBranchName, public readonly string $StorageUrl)
    {

    }
 }
