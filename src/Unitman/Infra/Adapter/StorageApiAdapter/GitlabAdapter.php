<?php

namespace App\Unitman\Infra\Adapter\StorageApiAdapter;

use App\Unitman\Business\Model\Project;
use App\Unitman\Business\Model\Project\ProjectCode;
use App\Unitman\Business\Model\Repo;
use App\Unitman\Business\Model\RepoAdapter\CheckAccessResponse;
use App\Unitman\Business\ReadModel\RepoTypeList;
use App\Unitman\Business\ReadModel\VetkaProekta;
use App\Unitman\Infra\Adapter\StorageApiAdapter\GitlabAdapter\GitlabClientFactory;
use Gitlab\ResultPager;

final class GitlabAdapter implements \App\Unitman\Infra\Adapter\StorageApiAdapter
{
    public function __construct(private GitlabClientFactory $clientFactory)
    {
    }

    public function isSupport(Repo\RepoType $repoType): bool
    {
        return $repoType == Repo\RepoType::GITLAB;
    }

    public function checkAccess(Repo $repo): CheckAccessResponse
    {
        $client = $this->clientFactory->makeClient($repo);
        $projects = (new ResultPager($client))->fetchAll($client->projects(), 'all');
        if (empty($projects)) {
            return CheckAccessResponse::fromError('Not found projects');
        }
        return CheckAccessResponse::success();
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

    public function poluchitVetkiProekta(Repo $repo, string $projectCode, ?string $query): array
    {
        $client = $this->clientFactory->makeClient($repo);
        $parameters = ['per_page' => 100];
        if (!empty($query)) {
            $parameters['search'] = $query;
        }
        $branches = $client->repositories()->branches($projectCode, $parameters);
        usort($branches, function ($a, $b) {
            $datetime1 = strtotime($a['commit']['created_at']);
            $datetime2 = strtotime($b['commit']['created_at']);
            return $datetime2 - $datetime1;
        });

        return array_map(fn(array $branch) => new VetkaProekta($branch['name']), $branches);
    }

    public function getUrlForInitProject(Repo $repo, Project $project): string
    {
        $url = $repo->getCredentials()->url;
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $urlWithoutScheme = preg_replace("/https?:\/\//misu", "", $url);
        $storageUrl = $scheme.'://oauth2:'.$repo->getCredentials()->token.'@'.$urlWithoutScheme;
        $projectUrl = $storageUrl . '/' . $project->getCode() . '.git';
        return $projectUrl;
    }
}
