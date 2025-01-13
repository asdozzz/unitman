<?php

namespace App\Unitman\Business\Model\Unit\ConfigUnita;

use App\Unitman\Business\Model\Unit\ConfigVariable;

readonly class KonfigDeistviya
{
    /**
     * @param array<ConfigVariable> $variables
     * @param array<string> $commands
     * */
    public function __construct(
        public string $id,
        public string $name,
        public array $variables,
        public array $commands,
    )
    {
    }

    static function fromArray(array $config): KonfigDeistviya
    {
        if (empty($config['id'])) {
            throw new \DomainException('unit.config.action.id_not_defined');
        }

        if (empty($config['name'])) {
            throw new \DomainException('unit.config.action.name_not_defined');
        }

        if (empty($config['commands'])) {
            throw new \DomainException('unit.config.action.commands_not_defined');
        }

        if (!is_array($config['commands'])) {
            throw new \DomainException('unit.config.action.commands_is_not_array');
        }

        $variables = [];
        if (!empty($config['variables'])) {
            if (!is_array($config['variables'])) {
                throw new \DomainException('unit.config.action.variables_is_not_array');
            }

            foreach ($config['variables'] as $variable) {
                $variables[] = ConfigVariable\ConfigVariableFactory::fromArray($variable);
            }
        }

        return new self(
            $config['id'],
            $config['name'],
            $variables,
            $config['commands']
        );
    }

    function getConfigVariableById(string $variableId): ConfigVariable
    {
        $result = null;

        foreach ($this->variables as $variable) {
           if ($variable->getId() === $variableId) {
               $result = $variable;
           }
        }

        if (empty($result)) {
            throw new \DomainException('unit.konfig.deistvie.variable_not_found_by_id');
        }

        return $result;
    }

    function toArray(): array
    {
        return array(
            'id' => $this->id,
            'name' => $this->name,
            'variables' => array_map(fn(ConfigVariable $variable) => $variable->toArray(), $this->variables),
            'commands' => $this->commands,
        );
    }

}
