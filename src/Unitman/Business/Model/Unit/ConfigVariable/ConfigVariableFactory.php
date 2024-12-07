<?php

namespace App\Unitman\Business\Model\Unit\ConfigVariable;

use App\Unitman\Business\Model\Unit\ConfigVariable;

final class ConfigVariableFactory
{
    static function fromArray(array $variableConfig): ConfigVariable
    {
        if (empty($variableConfig['type'])) {
            throw new \DomainException('unit.config_variable_factory.type_is_empty');
        }

        $id = new ConfigVariableId($variableConfig['id'] ?? '');
        $label = new ConfigVariableLabel($variableConfig['label'] ?? '');
        $defaultValue = new ConfigDefaultValue($variableConfig['defaultValue'] ?? '');

        return match ($variableConfig['type']) {
            IntegerConfigVariable::TYPE_CODE => new IntegerConfigVariable($id, $label, $defaultValue),
            FloatConfigVariable::TYPE_CODE => new FloatConfigVariable($id, $label, $defaultValue),
            StringConfigVariable::TYPE_CODE => new StringConfigVariable($id, $label,$defaultValue ),
            CollectionConfigVariable::TYPE_CODE => new CollectionConfigVariable($id, $label, $defaultValue, new ConfigVariable\CollectionConfigVariable\CollectionOptions($variableConfig['options'] ?? [])),
            UnitConfigVariable::TYPE_CODE => new UnitConfigVariable($id, $label, $defaultValue, ConfigVariable\UnitConfigVariable\UnitOptions::fromArray($variableConfig['options'] ?? [])),
            default => throw new \DomainException('unit.config_variable_factory.type_invalid')
        };
    }
}
