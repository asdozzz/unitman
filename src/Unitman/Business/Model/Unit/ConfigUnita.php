<?php

namespace App\Unitman\Business\Model\Unit;

use App\Unitman\Business\Model\Unit\ConfigUnita\KonfigServisa;
use App\Unitman\Business\Model\Unit\ConfigVariable\ConfigVariableFactory;

final class ConfigUnita
{
    /**
     * @var ConfigVariable[]
     * */
    private array $variables;
    private array $prepare;
    private array $resetPrepare;
    private array $up;
    private array $down;
    /**
     * @var KonfigServisa[]
     * */
    private array $services;

    public function __construct(
        array $variables,
        array $prepare,
        array $resetPrepare,
        array $up,
        array $down,
        array $services
    )
    {
        //TODO вынести вызов фабрики в тест кейс и использовать DI
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
        if (empty($services)) {
            throw new \DomainException('unit.config.services_is_empty');
        }
        $this->prepare = $prepare;
        $this->resetPrepare = $resetPrepare;
        $this->up = $up;
        $this->down = $down;

        $this->services = [];
        foreach ($services as $serviceName => $serviceData) {
            $this->services[] = KonfigServisa::fromServiceData($serviceName, $serviceData);
        }
    }

    function toArray(): array
    {
        return [
            'variables' => array_map(fn(ConfigVariable $variable): array => $variable->toArray(), $this->variables),
            'prepare' => $this->prepare,
            'reset_prepare' => $this->resetPrepare,
            'up' => $this->up,
            'down' => $this->down,
            'services' => array_map(fn(KonfigServisa $service): array => $service->toArray(), $this->services),
        ];
    }

    static function fromArray(array $cfg): static
    {
        return new static(
            isset($cfg['variables']) && is_array($cfg['variables'])?$cfg['variables']:[],
            isset($cfg['prepare']) && is_array($cfg['prepare'])?$cfg['prepare']:[],
            isset($cfg['reset_prepare']) && is_array($cfg['reset_prepare'])?$cfg['reset_prepare']:[],
            isset($cfg['up']) && is_array($cfg['up'])?$cfg['up']:[],
            isset($cfg['down']) && is_array($cfg['down'])?$cfg['down']:[],
            isset($cfg['services']) && is_array($cfg['services'])?$cfg['services']:[],
        );
    }

    /**
     * @param VariableValue[] $values
     * */
    function validateValues(array $values): array
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

    public function getVariableTypeMap(): array
    {
        $map = [];

        foreach ($this->variables as $variable) {
            $map[$variable->getId()] = $variable->getType();
        }

        return $map;
    }

    /**
     * @return array
     */
    public function getVariables(): array
    {
        return $this->variables;
    }

    /**
     * @return array
     */
    public function getPrepare(): array
    {
        return $this->prepare;
    }

    /**
     * @return array
     */
    public function getResetPrepare(): array
    {
        return $this->resetPrepare;
    }

    /**
     * @return array
     */
    public function getUp(): array
    {
        return $this->up;
    }

    /**
     * @return array
     */
    public function getDown(): array
    {
        return $this->down;
    }

    /**
     * @return KonfigServisa[]
     * */
    public function getServices(): array
    {
        return $this->services;
    }
}
