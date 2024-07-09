<?php

namespace App\Unitman\Infra\Adapter;

use App\Unitman\Business\Model\Project;
use App\Unitman\Business\Model\Project\ProjectCode;
use App\Unitman\Business\Model\Repo;
use App\Unitman\Business\Model\RepoAdapter\CheckAccessResponse;
use App\Unitman\Business\ReadModel\RepoTypeList;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('unitman.storage_api_adapter')]
interface StorageApiAdapter
{
    public function isSupport(Repo\RepoType $repoType): bool;
    public function checkAccess(Repo $repo): CheckAccessResponse;

    public function poluchitUrlHranilisha(?string $repoUrl): string;

    public function getRepoTypeModel(): RepoTypeList;

    public function poluchitVetkiProekta(Repo $repo, string $projectCode, ?string $query): array;

    public function getUrlForInitProject(Repo $repo, Project $project): string;
}
