<?php

declare(strict_types=1);

namespace App\Unitman\Infra\Adapter\StorageApiAdapter;

use App\Unitman\Business\Model\Project;
use App\Unitman\Business\Model\Repo;
use App\Unitman\Business\Port\Project\UmeetPoluchatUrlKlonaProekta;
use App\Unitman\Business\Port\Project\UmeetProveryatPushTokenom;
use App\Unitman\Business\Port\Project\UmeetProveryatPushVProekt;
use App\Unitman\Business\Port\Repo\UmeetProveryatTokenHranilisha;
use App\Unitman\Infra\Adapter\StorageApiAdapterFactory;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Мост между бизнес-портами workspace и конкретным адаптером хостинга.
 */
final class WorkspaceProjectStorageAdapter implements UmeetProveryatPushVProekt, UmeetPoluchatUrlKlonaProekta, UmeetProveryatPushTokenom, UmeetProveryatTokenHranilisha
{
    /**
     * @var iterable<\App\Unitman\Infra\Adapter\StorageApiAdapter>
     */
    private iterable $adapters;

    public function __construct(
        #[AutowireIterator('unitman.storage_api_adapter')] iterable $adapters,
        private readonly StorageApiAdapterFactory $storageApiAdapterFactory,
    ) {
        $this->adapters = $adapters;
    }

    public function canPush(Repo $repo, Project $project): bool
    {
        foreach ($this->adapters as $adapter) {
            if ($adapter->isSupport($repo->getType())) {
                return $adapter->canPush($repo, $project);
            }
        }

        throw new \Exception(sprintf(
            'StorageApiAdapter for repoType=%s not found',
            $repo->getType()->value,
        ));
    }

    public function canPushTokenom(string $token, Repo $repo, Project $project): bool
    {
        foreach ($this->adapters as $adapter) {
            if ($adapter->isSupport($repo->getType())) {
                return $adapter->canPushTokenom($token, $repo, $project);
            }
        }

        throw new \Exception(sprintf(
            'StorageApiAdapter for repoType=%s not found',
            $repo->getType()->value,
        ));
    }

    public function proveritToken(string $token, Repo $repo): bool
    {
        foreach ($this->adapters as $adapter) {
            if ($adapter->isSupport($repo->getType())) {
                return $adapter->proveritToken($token, $repo);
            }
        }

        throw new \Exception(sprintf(
            'StorageApiAdapter for repoType=%s not found',
            $repo->getType()->value,
        ));
    }

    public function poluchitUrlKlonaProekta(Repo $repo, Project $project): string
    {
        return $this->storageApiAdapterFactory->getUrlForInitProject($repo, $project);
    }
}
