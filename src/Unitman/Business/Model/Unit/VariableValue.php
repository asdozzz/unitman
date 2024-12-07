<?php

namespace App\Unitman\Business\Model\Unit;

final class VariableValue
{
    private string $id;
    private string $value;
    private string $type;

    public function __construct(
        string $id,
        string $value,
        string $type = 'string'
    )
    {
        if (empty($id)) {
            throw new \DomainException('unit.variable_value.id_is_empty');
        }

        if (empty($value)) {
            throw new \DomainException('unit.variable_value.value_is_empty');
        }
        if (empty($type)) {
            throw new \DomainException('unit.variable_value.type_is_empty');
        }
        $this->id = $id;
        $this->value = $value;
        $this->type = $type;
    }

    function toArray(): array
    {
        return [
            'id' => $this->id,
            'value' => $this->value,
            'type' => $this->type
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

    public function getType(): string
    {
        return $this->type;
    }
}
