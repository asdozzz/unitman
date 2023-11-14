<?php

namespace App\Unitman\Business\Model\Unit\State;

use App\Unitman\Business\Model\Unit;

final class Sozdan extends AbstractState
{

    public function getCode(): string
    {
        return 'SOZDAN';
    }

    /**
     * @inheritDoc
     */
    public function getNextStates(): array
    {
        return [
            new VOcherediNaSborku(),
            new VOcherediNaUdalenie()
        ];
    }

    public function getCommands(Unit $unit): array
    {
        return [StateUserCommand::nachatSborku, StateUserCommand::nachatUdalenie];
    }
}
