<?php

namespace App\Unitman\Business\Model\Unit;

final class VariableValue
{
    private string $id;
    private string $value;

    public function __construct(
        string $id,
        string $value
    )
    {
        if (empty($id)) {
            throw new \DomainException('unit.variable_value.id_is_empty');
        }

        if (empty($value)) {
            throw new \DomainException('unit.variable_value.value_is_empty');
        }
        $this->id = $id;
        $this->value = $value;
    }

    function toArray()
    {
        return [
            'id' => $this->id,
            'value' => $this->value
        ];
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }
}
