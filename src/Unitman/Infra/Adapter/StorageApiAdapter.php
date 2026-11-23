<?php

namespace App\Unitman\Infra\Adapter;

use App\Unitman\Business\Model\Project;
use App\Unitman\Business\Model\Repo;
use App\Unitman\Business\Model\RepoAdapter\CheckAccessResponse;
use App\Unitman\Business\ReadModel\RepoTypeList;
use App\Unitman\Business\ReadModel\SostoyanieVetki;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('unitman.storage_api_adapter')]
interface StorageApiAdapter
{
    public function isSupport(Repo\RepoType $repoType): bool;
    public function checkAccess(Repo $repo): CheckAccessResponse;

    public function poluchitUrlHranilisha(?string $repoUrl): string;

    public function getRepoTypeModel(): RepoTypeList;

    public function poluchitVetkiProekta(Repo $repo, string $projectCode, ?string $query): array;

    /**
     * Состояние ветки и время её HEAD-коммита.
     */
    public function poluchitSostoyanieVetki(
        Repo $repo,
        string $projectCode,
        string $branch,
        string $mainBranch,
    ): SostoyanieVetki;

    /**
     * Создать ветку в проекте хранения от указанной ветки.
     */
    public function sozdatVetkuVHranilishe(Repo $repo, string $projectCode, string $nazvanieVetki, string $otVetki, string $token): void;

    public function getUrlForInitProject(Repo $repo, Project $project): string;

    /**
     * Может ли пользователь пушить в проект хостинга.
     */
    public function canPush(Repo $repo, Project $project): bool;

    /**
     * Может ли указанный (личный) токен пушить в проект хостинга.
     */
    public function canPushTokenom(string $token, Repo $repo, Project $project): bool;

    /**
     * Валиден ли произвольный токен хранилища для указанного Repo.
     */
    public function proveritToken(string $token, Repo $repo): bool;

    public function poluchitKonfigIzHranilisha(Repo $repo, string $projectCode, string $branchName): string;

    public function poluchitProektiHranilisha(Repo $repo, ?string $query): array;
}
