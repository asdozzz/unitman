<?php

declare(strict_types=1);

namespace App\Unitman\Infra\Jobs\SobitiyaIzHranilisha\StorageTypeAdapter;

use App\Unitman\Business\Model\Repo\RepoType;
use App\Unitman\Business\Model\SobitieIzHranilisha\DannieSobitiya;
use App\Unitman\Business\Model\SobitieIzHranilisha\SobitiePayload;

/**
 * GitVerse настраивает вебхуки на custom URL и присылает события push и
 * pull request в формате, близком к GitHub. Нормализацию переиспользуем
 * у GithubAdapter; при расхождении формата правится только этот адаптер.
 */
final class GitverseAdapter implements StorageTypeAdapter
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
        return $repoType === RepoType::GITVERSE;
    }

    public function poluchitDannieSobitiya(SobitiePayload $payload, string $mainBranch): DannieSobitiya
    {
        return $this->githubAdapter->poluchitDannieSobitiya($payload, $mainBranch);
    }
}
