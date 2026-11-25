<?php

declare(strict_types=1);

namespace App\Unitman\Infra\Jobs\SobitiyaIzHranilisha\StorageTypeAdapter;

use App\Unitman\Business\Model\Repo\RepoType;
use App\Unitman\Business\Model\SobitieIzHranilisha\DannieSobitiya;
use App\Unitman\Business\Model\SobitieIzHranilisha\SobitiePayload;

/**
 * Gitea, Forgejo и Codeberg присылают push и pull request события в формате,
 * совместимом с GitHub, поэтому нормализацию переиспользуем у GithubAdapter.
 */
final class GiteaFamilyAdapter implements StorageTypeAdapter
{
    public function __construct(private readonly GithubAdapter $githubAdapter)
    {
    }

    public function esliValidnoeSobitie(SobitiePayload $payload): bool
    {
        return $this->githubAdapter->esliValidnoeSobitie($payload);
    }

    public function isSupport(RepoType $repoType): bool
    {
        return in_array(
            $repoType,
            [RepoType::GITEA, RepoType::FORGEJO, RepoType::CODEBERG],
            true,
        );
    }

    public function poluchitDannieSobitiya(SobitiePayload $payload, string $mainBranch): DannieSobitiya
    {
        return $this->githubAdapter->poluchitDannieSobitiya($payload, $mainBranch);
    }
}
