<?php

namespace App\App\Infra\Workflow;

final class WorkflowCollection
{
    private iterable $collection;

    public function __construct(iterable $collection)
    {
        $this->collection = $collection;
    }

    /**
     * @return AppWorkflowInterface[]
     */
    public function getCollection(): array
    {
        $arr = [];
        foreach ($this->collection as $item) {
            $arr[] = get_class($item);
        }

        return $arr;
    }
}
