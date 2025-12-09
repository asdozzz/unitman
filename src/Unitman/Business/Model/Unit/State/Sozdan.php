<?php

namespace App\Unitman\Business\Model\Unit\State;

use App\Unitman\Business\Model\Unit;

final class Sozdan extends AbstractState
{

    const CODE = 'SOZDAN';

    public function getCode(): string
    {
        return self::CODE;
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
        return [StateUserCommand::nachatUdalenie];
    }
}
