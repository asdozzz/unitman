<?php

namespace App\Unitman\Business\Model\Unit\State;

final class Udalen extends AbstractState
{

    public function getCode(): string
    {
        return 'UDALEN';
    }

    /**
     * @inheritDoc
     */
    public function getNextStates(): array
    {
        return [];
    }

    public function getCommands(): array
    {
        return [];
    }
}
