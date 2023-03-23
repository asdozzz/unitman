<?php

namespace App\Unitman\Business\Model\Unit\State;

final class OshibkaSborki extends AbstractState
{

    public function getCode(): string
    {
        return 'OSHIBKA_SBORKI';
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

    public function getCommands(): array
    {
        return [
            StateUserCommand::nachatUdalenie,
            StateUserCommand::nachatSborku
        ];
    }
}
