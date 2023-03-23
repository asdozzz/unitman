<?php

namespace App\Unitman\Business\Model\Unit\State;

final class VOcherediNaSborku extends AbstractState
{

    public function getCode(): string
    {
        return 'JDET_RESULTATI_SBORKI';
    }

    /**
     * @inheritDoc
     */
    public function getNextStates(): array
    {
        return [
            new OshibkaSborki(),
            new Sobran()
        ];
    }

    public function getCommands(): array
    {
        return [];
    }
}
