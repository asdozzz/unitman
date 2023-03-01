<?php

namespace App\Unitman\Infra\Adapter;

use App\Unitman\Business\Model\Repo;
use App\Unitman\Business\Model\RepoAdapter\CheckAccessResponse;
use App\Unitman\Business\Port\CanCheckAccessToRepo;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

final class StorageApiAdapterFactory implements CanCheckAccessToRepo
{
    /**
     * @var iterable<StorageApiAdapter>
     * */
    private iterable $adapters;
    public function __construct(#[TaggedIterator('unitman.storage_api_adapter')] iterable $adapters)
    {
        $this->adapters = $adapters;
    }

    private function getAdapterByRepo(Repo $repo): StorageApiAdapter
    {
        $result = null;

        foreach ($this->adapters as $adapter) {
            if ($adapter->isSupport($repo->getType())) {
                $result = $adapter;
            }
        }

        if (empty($result)) {
            throw new \Exception(sprintf('StorageApiAdapter for repoType=%s not found', $repo->getType()->value));
        }

        return $result;
    }

    public function checkAccess(Repo $repo): CheckAccessResponse
    {
        $adapter = $this->getAdapterByRepo($repo);
        return $adapter->checkAccess($repo);
    }
}
