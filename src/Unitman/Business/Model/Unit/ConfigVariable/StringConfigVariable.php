<?php

namespace App\Unitman\Business\Model\Unit\ConfigVariable;

use App\Unitman\Business\Model\Unit\ConfigVariable;

final class StringConfigVariable implements ConfigVariable
{
    const TYPE_CODE = 'string';

    public function __construct(
        public readonly ConfigVariableId $id,
        public readonly ConfigVariableLabel $label,
        public readonly ConfigDefaultValue $defaultValue)
    {
    }

    function toArray(): array
    {
        return array(
            'id' => (string)$this->id,
            'label' => (string)$this->label,
            'type' => self::TYPE_CODE,
            'defaultValue' => (string)$this->defaultValue
        );
    }

    function getId(): string
    {
        return (string)$this->id;
    }

    function validateValue(string $value): ?string
    {
        if (empty($value)) {
            return sprintf('value for variable with id=%s is empty', $this->getId());
        }

        return null;
    }
}
