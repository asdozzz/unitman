<?php

declare(strict_types=1);

namespace App\Unitman\Infra\Adapter\StorageApiAdapter;

use App\Unitman\Business\Model\Repo;
use App\Unitman\Business\ReadModel\RepoTypeList;

final class ForgejoAdapter implements \App\Unitman\Infra\Adapter\StorageApiAdapter
{
    use GiteaFamilyOperations;

    public function isSupport(Repo\RepoType $repoType): bool
    {
        return $repoType === Repo\RepoType::FORGEJO;
    }

    public function getRepoTypeModel(): RepoTypeList
    {
        return new RepoTypeList(Repo\RepoType::FORGEJO->value, Repo\RepoType::FORGEJO->name);
    }
}
