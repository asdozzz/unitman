<?php

namespace App\Unitman\Infra\Jobs\SobitiyaIzHranilisha\StorageTypeAdapter;

use App\Unitman\Business\Model\Repo\RepoType;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class StorageTypeAdapterFactory
{
    /**
     * @var iterable<StorageTypeAdapter>
     * */
    private iterable $adapters;

    public function __construct(#[AutowireIterator('unitman.storage_type_for_hook_adapter')] iterable $adapters)
    {
        $this->adapters = $adapters;
    }

    function getAdapter(RepoType $repoType): StorageTypeAdapter
    {
        $result = null;

        foreach ($this->adapters as $adapter) {
            if ($adapter->isSupport($repoType)) {
                $result = $adapter;
            }
        }

        if (empty($result)) {
            throw new \Exception(sprintf('StorageTypeAdapter for repoType=%s not found', $repoType->value));
        }

        return $result;
    }
}
