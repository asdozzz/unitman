<?php

namespace App\Unitman\Business\Model\Unit\State;

final class UdalenVruchnuyu extends AbstractState
{

    public function getCode(): string
    {
        return 'UDALEN_VRUCHNUYU';
    }

    /**
     * @inheritDoc
     */
    public function getNextStates(): array
    {
        return [];
    }
}
