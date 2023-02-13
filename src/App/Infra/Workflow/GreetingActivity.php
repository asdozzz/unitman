<?php

namespace App\App\Infra\Workflow;
use App\App\Infra\Workflow\AppActivityInterface;
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;

#[ActivityInterface(prefix: 'SimpleActivity.')]
class GreetingActivity implements AppActivityInterface
{
    #[ActivityMethod(name: "ComposeGreeting")]
    public function composeGreeting(string $greeting, string $name): string
    {
        return $greeting . ' ' . $name;
    }
}
