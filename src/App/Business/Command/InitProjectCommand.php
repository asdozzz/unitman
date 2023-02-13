<?php

namespace App\App\Business\Command;

final class InitProjectCommand
{
    public function __construct(public string $ProjectId, public string $MainBranchName, public string $StorageUrl)
    {

    }
 }
