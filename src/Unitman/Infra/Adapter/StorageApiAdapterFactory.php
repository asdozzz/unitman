<?php

namespace App\Unitman\Infra\Adapter;

use App\Unitman\Business\Command\Repo\GetRepoTypeList;
use App\Unitman\Business\Model\Project;
use App\Unitman\Business\Model\Project\ProjectCode;
use App\Unitman\Business\Model\Repo;
use App\Unitman\Business\Model\Repo\RepoType;
use App\Unitman\Business\Model\RepoAdapter\CheckAccessResponse;
use App\Unitman\Business\Port\Project\UmeetPoluchatKonfigProekta;
use App\Unitman\Business\Port\Project\UmeetPoluchatSpisokVetokProekta;
use App\Unitman\Business\Port\Repo\CanCheckAccessToRepo;
use App\Unitman\Business\Port\Repo\CanGetRepoTypeList;
use App\Unitman\Business\Port\Repo\UmeetPoluchatSpisokProektovHranilisha;
use App\Unitman\Business\Port\Repo\UmeetPoluchatUrlHranilisha;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class StorageApiAdapterFactory implements CanCheckAccessToRepo, UmeetPoluchatUrlHranilisha, CanGetRepoTypeList, UmeetPoluchatSpisokVetokProekta, UmeetPoluchatKonfigProekta, UmeetPoluchatSpisokProektovHranilisha
{
    /**
     * @var iterable<StorageApiAdapter>
     * */
    private iterable $adapters;
    public function __construct(#[AutowireIterator('unitman.storage_api_adapter')] iterable $adapters)
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

    function poluchitUrlHranilisha(RepoType $repoType, ?string $repoUrl): string
    {
        $adapter = $this->getAdapterByRepo($repoType);
        return $adapter->poluchitUrlHranilisha($repoUrl);
    }


    function getRepoTypeList(GetRepoTypeList $query): array
    {
        $res = [];
        foreach ($this->adapters as $adapter) {
            $res[] = $adapter->getRepoTypeModel();
        }
        return $res;
    }

    function poluchitVetkiProekta(Repo $repo, string $projectCode, ?string $query): array
    {
        $adapter = $this->getAdapterByRepo($repo->getType());
        return $adapter->poluchitVetkiProekta($repo, $projectCode, $query);
    }

    function getUrlForInitProject(Repo $repo, Project $project): string
    {
        $adapter = $this->getAdapterByRepo($repo->getType());
        return $adapter->getUrlForInitProject($repo, $project);
    }

    function poluchitKonfigIzHranilisha(Repo $repo, string $projectCode, string $branchName): string
    {
        $adapter = $this->getAdapterByRepo($repo->getType());
        return $adapter->poluchitKonfigIzHranilisha($repo, $projectCode, $branchName);
    }

    function poluchitSpisokProektovHranilisha(Repo $repo, ?string $query)
    {
        $adapter = $this->getAdapterByRepo($repo->getType());
        return $adapter->poluchitProektiHranilisha($repo, $query);
    }
}
