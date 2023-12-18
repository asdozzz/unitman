<?php

namespace App\Unitman\Infra\Adapter;

use App\Unitman\Business\Model\Repo;
use App\Unitman\Business\Model\Repo\RepoType;
use App\Unitman\Business\Model\RepoAdapter\CheckAccessResponse;
use App\Unitman\Business\Port\CanCheckAccessToRepo;
use App\Unitman\Business\Port\UmeetPoluchatUrlHranilisha;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

final class StorageApiAdapterFactory implements CanCheckAccessToRepo, UmeetPoluchatUrlHranilisha
{
    /**
     * @var iterable<StorageApiAdapter>
     * */
    private iterable $adapters;
    public function __construct(#[TaggedIterator('unitman.storage_api_adapter')] iterable $adapters)
    {
        $this->adapters = $adapters;
    }

    private function getAdapterByRepo(RepoType $repoType): StorageApiAdapter
    {
        $result = null;

        foreach ($this->adapters as $adapter) {
            if ($adapter->isSupport($repoType)) {
                $result = $adapter;
            }
        }

        if (empty($result)) {
            throw new \Exception(sprintf('StorageApiAdapter for repoType=%s not found', $repoType->value));
        }

        return $result;
    }

    public function checkAccess(Repo $repo): CheckAccessResponse
    {
        $adapter = $this->getAdapterByRepo($repo->getType());
        return $adapter->checkAccess($repo);
    }

    function poluchitUrlHranilisha(RepoType $repoType, ?string $repoUrl)
    {
        $adapter = $this->getAdapterByRepo($repoType);
        return $adapter->poluchitUrlHranilisha($repoUrl);
    }
}
