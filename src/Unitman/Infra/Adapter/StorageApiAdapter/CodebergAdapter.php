<?php

declare(strict_types=1);

namespace App\Unitman\Infra\Adapter\StorageApiAdapter;

use App\Unitman\Business\Model\Repo;
use App\Unitman\Business\ReadModel\RepoTypeList;

final class CodebergAdapter implements \App\Unitman\Infra\Adapter\StorageApiAdapter
{
    use GiteaFamilyOperations;

    private const CODEBERG_URL = 'https://codeberg.org';

    public function isSupport(Repo\RepoType $repoType): bool
    {
        return $repoType === Repo\RepoType::CODEBERG;
    }

    public function getRepoTypeModel(): RepoTypeList
    {
        return new RepoTypeList(Repo\RepoType::CODEBERG->value, Repo\RepoType::CODEBERG->name);
    }

    public function poluchitUrlHranilisha(?string $repoUrl): string
    {
        // Codeberg — фиксированный SaaS-хостинг, свой URL ему не нужен.
        return self::CODEBERG_URL;
    }
}
