<?php

namespace App\Unitman\Business\Model\Unit\ConfigVariable;

use App\Unitman\Business\Model\Unit\AbstractConfigVariable;
use App\Unitman\Business\Model\Unit\ConfigVariable;

final class IntegerConfigVariable extends AbstractConfigVariable implements ConfigVariable
{
    const TYPE_CODE = 'integer';

    public function __construct(
        public readonly ConfigVariableId $id,
        public readonly ConfigVariableLabel $label,
        public readonly ConfigDefaultValue $defaultValue)
    {
    }

    function getId(): string
    {
        return (string)$this->id;
    }

    /**
     * @psalm-suppress TypeDoesNotContainType
     * */
    function validateValue(string $value): ?string
    {
        if (empty($value)) {
            return sprintf('value for variable with id=%s is empty', $this->getId());
        }

        if (!is_int(intval($value))) {
            return sprintf('value for variable with id=%s is not integer', $this->getId());
        }

        return null;
    }

    function getType(): string
    {
        return self::TYPE_CODE;
    }

    function getLabel(): string
    {
        return (string)$this->label;
    }

    function getDefaultValue(): string
    {
        return (string)$this->defaultValue;
    }
}
