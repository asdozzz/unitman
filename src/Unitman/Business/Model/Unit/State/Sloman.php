<?php

namespace App\Unitman\Business\Model\Unit\State;

use App\Unitman\Business\Model\Unit;

final class Sloman extends AbstractState
{

    const CODE = 'SLOMAN';

    public function getCode(): string
    {
        return self::CODE;
    }

    public function getNextStates(): array
    {
        return [new UdalenVruchnuyu()];
    }

    public function getCommands(Unit $unit): array
    {
        return [
            StateUserCommand::udalitVruchnuyu
        ];
    }
}
