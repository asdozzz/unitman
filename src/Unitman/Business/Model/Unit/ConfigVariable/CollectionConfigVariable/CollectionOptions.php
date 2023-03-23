<?php

namespace App\Unitman\Business\Model\Unit\ConfigVariable\CollectionConfigVariable;

final class CollectionOptions
{
    private array $options;

    public function __construct(array $options = [])
    {
        if (empty($options)) {
            throw new \DomainException('unit.config.variable.collection_options_is_empty');
        }
        $this->options = array_map(fn(array $option) => new CollectionOption($option['id']??'', $option['name']??''), $options);
    }

    function toArray(): array
    {
        return array_map(fn(CollectionOption $option) => $option->toArray(), $this->options);
    }
}
