<?php

namespace App\Unitman\Business\Model\Unit\ConfigVariable\CollectionConfigVariable;

final class CollectionOption
{
    private string $id;
    private string $name;

    public function __construct(
        string $id,
        string $name
    )
    {
        if (empty($id)) {
            throw new \DomainException('unit.config_variable.collection_option.id_is_empty');
        }

        if (empty($name)) {
            throw new \DomainException('unit.config_variable.collection_option.name_is_empty');
        }

        $this->id = $id;
        $this->name = $name;
    }

    function toArray()
    {
        return array(
            'id' => $this->id,
            'name' => $this->name
        );
    }
}
