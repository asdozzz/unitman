<?php

namespace App\Unitman\Business\Model\Unit\State;

use App\Unitman\Business\Model\Unit;

abstract class AbstractState
{
    abstract public function getCode(): string;
    /**
     * @return AbstractState[]
     * */
    abstract public function getNextStates(): array;
    /**
     * @return StateUserCommand[]
     * */
    abstract public function getCommands(Unit $unit): array;

    public function newState(AbstractState $state): AbstractState
    {
        $nextCodes = array_map(fn(AbstractState $state) => $state->getCode(), $this->getNextStates());
        if (!in_array($state->getCode(), $nextCodes)) {
            throw new \DomainException(sprintf('Invalid new state, allowed: %s', join(',', $nextCodes)));
        }

        return $state;
    }

    function toArray(Unit $unit): array
    {
        $arr = array_map(fn(StateUserCommand $command) => $command->value, $this->getCommands($unit));

        return [
            'code' => $this->getCode(),
            'commands' => $arr
        ];
    }
}
