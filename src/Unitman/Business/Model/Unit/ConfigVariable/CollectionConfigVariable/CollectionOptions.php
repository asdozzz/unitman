<?php

namespace App\Unitman\Business\Model\Unit\ConfigVariable\CollectionConfigVariable;

final class CollectionOptions
{
    private array $options;

    public function __construct(array $options = [])
    {
        $temp = [];

        if (!empty($options['options'])) {
            $temp = $options['options'];
        } else if (!empty($options) && !empty($options[0]['id'])) {
            $temp = $options;
        }

        if (empty($temp)) {
            throw new \DomainException('unit.config.variable.collection_options_is_empty');
        }
        $this->options = array_map(fn(array $option) => new CollectionOption($option['id']??'', $option['name']??''), $temp);
    }

    function toArray(): array
    {
        return ['options' => array_map(fn(CollectionOption $option): array => $option->toArray(), $this->options)];
    }
}
