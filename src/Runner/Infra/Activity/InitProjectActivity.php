<?php

namespace App\Runner\Infra\Activity;

use App\Runner\Business\Command\InitProjectCommand;
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;

#[ActivityInterface(prefix:"")]
interface InitProjectActivity
{
    #[ActivityMethod(name:"InitProjectActivity")]
    public function execute(InitProjectCommand $command): string;
}
