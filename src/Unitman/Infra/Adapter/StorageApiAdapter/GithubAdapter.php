<?php

namespace App\Unitman\Infra\Adapter\StorageApiAdapter;

use App\Unitman\Business\Model\Repo;
use App\Unitman\Business\Model\RepoAdapter\CheckAccessResponse;
use App\Unitman\Infra\Adapter\StorageApiAdapter\GithubAdapter\GithubClientFactory;

final class GithubAdapter implements \App\Unitman\Infra\Adapter\StorageApiAdapter
{
    const GITHUB_REPO_URL = 'https://github.com';
    public function __construct(
        private GithubClientFactory $githubClientFactory
    )
    {
    }

    public function isSupport(Repo\RepoType $repoType): bool
    {
        return $repoType == Repo\RepoType::GITHUB;
    }

    public function checkAccess(Repo $repo): CheckAccessResponse
    {
        $client = $this->githubClientFactory->makeClient($repo);

        $repositories = $client->currentUser()->repositories();

        if (empty($repositories)) {
            return CheckAccessResponse::fromError('Not found repositories');
        }

        return CheckAccessResponse::success();
    }

    public function poluchitUrlHranilisha(?string $repoUrl): string
    {
        return self::GITHUB_REPO_URL;
    }
}
