<?php

namespace App\Unitman\Infra\Adapter\StorageApiAdapter;

use App\Unitman\Business\Model\Project\ProjectCode;
use App\Unitman\Business\Model\Repo;
use App\Unitman\Business\Model\RepoAdapter\CheckAccessResponse;
use App\Unitman\Business\ReadModel\RepoTypeList;

final class GitlabAdapter implements \App\Unitman\Infra\Adapter\StorageApiAdapter
{

    public function isSupport(Repo\RepoType $repoType): bool
    {
        return $repoType == Repo\RepoType::GITLAB;
    }

    public function checkAccess(Repo $repo): CheckAccessResponse
    {
        throw new \Exception('NEED REALIZATION');
    }

    public function poluchitUrlHranilisha(?string $repoUrl): string
    {
        if (empty($repoUrl)) {
            throw new \Exception('repo.url_is_empty');
        }

        return $repoUrl;
    }

    public function getRepoTypeModel(): RepoTypeList
    {
        return new RepoTypeList(Repo\RepoType::GITLAB->value, Repo\RepoType::GITLAB->name);
    }

    public function poluchitVetkiProekta(Repo $repo, string $projectCode): array
    {
        throw new \Exception('NEED REALIZATION');
    }
}
