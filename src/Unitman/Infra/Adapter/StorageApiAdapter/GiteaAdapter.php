<?php

declare(strict_types=1);

namespace App\Unitman\Infra\Adapter\StorageApiAdapter;

use App\Unitman\Business\Model\Repo;
use App\Unitman\Business\ReadModel\RepoTypeList;

final class GiteaAdapter implements \App\Unitman\Infra\Adapter\StorageApiAdapter
{
    use GiteaFamilyOperations;

    public function isSupport(Repo\RepoType $repoType): bool
    {
        return $repoType === Repo\RepoType::GITEA;
    }

    public function getRepoTypeModel(): RepoTypeList
    {
        return new RepoTypeList(Repo\RepoType::GITEA->value, Repo\RepoType::GITEA->name);
    }
}
