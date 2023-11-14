<?php

namespace App\Unitman\Business\Model\Unit;

use App\Unitman\Business\Model\Unit\ConfigVariable\ConfigVariableFactory;

final class ConfigUnita
{
    private array $variables;
    private array $prepare;
    private array $resetPrepare;
    private array $up;
    private array $down;

    public function __construct(
        array $variables,
        array $prepare,
        array $resetPrepare,
        array $up,
        array $down
    )
    {
        $this->variables = array_map(fn(array $item) => ConfigVariableFactory::fromArray($item), $variables);
        if (empty($prepare)) {
            throw new \DomainException('unit.config.prepare_is_empty');
        }
        if (empty($resetPrepare)) {
            throw new \DomainException('unit.config.resetPrepare_is_empty');
        }
        if (empty($up)) {
            throw new \DomainException('unit.config.up_is_empty');
        }
        if (empty($down)) {
            throw new \DomainException('unit.config.down_is_empty');
        }
        $this->prepare = $prepare;
        $this->resetPrepare = $resetPrepare;
        $this->up = $up;
        $this->down = $down;
    }

    function toArray()
    {
        return [
            'variables' => array_map(fn(ConfigVariable $variable) => $variable->toArray(), $this->variables),
            'prepare' => $this->prepare,
            'reset_prepare' => $this->resetPrepare,
            'up' => $this->up,
            'down' => $this->down,
        ];
    }

    static function fromArray(array $cfg): static
    {
        return new static(
            is_array($cfg['variables'])?$cfg['variables']:[],
            is_array($cfg['prepare'])?$cfg['prepare']:[],
            is_array($cfg['reset_prepare'])?$cfg['reset_prepare']:[],
            is_array($cfg['up'])?$cfg['up']:[],
            is_array($cfg['down'])?$cfg['down']:[],
        );
    }

    /**
     * @param VariableValue[] $values
     * */
    function validateValues(array $values)
    {
        $formatValues = [];

        foreach ($values as $value) {
            $formatValues[$value->getId()] = $value->getValue();
        }

        $errs = [];
        foreach ($this->variables as $variable) {
            if (!isset($formatValues[$variable->getId()])) {
                $errs[] = sprintf('value for variable with id=%s not defined', $variable->getId());
            } else {
                $err = $variable->validateValue($formatValues[$variable->getId()]);
                if (!empty($err)) {
                    $errs[] = $err;
                }
            }
        }

        return $errs;
    }
}
