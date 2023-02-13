<?php

namespace App\App\Infra\Workflow;

use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

final class ActivityCollection
{
    private iterable $collection;
    public function __construct(iterable $collection)
    {
        $this->collection = $collection;
    }

    /**
     * @return AppActivityInterface[]
     */
    public function getCollection(): iterable
    {
        return [...$this->collection];
    }


}
