<?php

namespace App\Unitman\Infra\Adapter\StorageApiAdapter;

use App\Unitman\Business\Model\Project;
use App\Unitman\Business\Model\Project\StatusVetki;
use App\Unitman\Business\Model\Repo;
use App\Unitman\Business\Model\RepoAdapter\CheckAccessResponse;
use App\Unitman\Business\ReadModel\ProektHranilisha;
use App\Unitman\Business\ReadModel\RepoTypeList;
use App\Unitman\Business\ReadModel\SostoyanieVetki;
use App\Unitman\Business\ReadModel\VetkaProekta;
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

    public function getRepoTypeModel(): RepoTypeList
    {
        return new RepoTypeList(Repo\RepoType::GITHUB->value, Repo\RepoType::GITHUB->name);
    }

    public function poluchitVetkiProekta(Repo $repo, string $projectCode,?string $query): array
    {
        $client = $this->githubClientFactory->makeClient($repo);
        list($login, $code) = explode('/', $projectCode);
        $branches = $client->repositories()->branches($login, $code, null, ['per_page' => 100]);
        if (!empty($query)) {
            $tmp = array_filter($branches, function (array $branch) use ($query) {
                return strpos($branch['name'], $query) !== false;
            });
            $branches = array_values($tmp);
        }

        return array_map(fn(array $branch) => new VetkaProekta($branch['name']), $branches);
    }

    public function poluchitSostoyanieVetki(
        Repo $repo,
        string $projectCode,
        string $branch,
        string $mainBranch,
    ): SostoyanieVetki {
        $client = $this->githubClientFactory->makeClient($repo);
        [$login, $code] = explode('/', $projectCode, 2);

        try {
            $branchData = $client->repositories()->branches($login, $code, $branch);
        } catch (\Throwable $e) {
            return new SostoyanieVetki(
                $branch,
                $this->eto404($e) ? StatusVetki::DELETED : StatusVetki::UNKNOWN,
            );
        }

        if (($branchData['name'] ?? null) !== $branch) {
            return new SostoyanieVetki($branch, StatusVetki::DELETED);
        }

        $unixtime = $this->izvlechUnixtime(
            $branchData['commit']['commit']['committer']['date'] ?? null,
            $branchData['commit']['commit']['author']['date'] ?? null,
        );

        if ($branch === $mainBranch) {
            return new SostoyanieVetki($branch, StatusVetki::ACTIVE, $unixtime);
        }

        /*
         * Слияние подтверждаем только merged pull request. Проверка
         * «нет уникальных коммитов» не подходит: свежая ветка без коммитов
         * тоже не имеет уникальных коммитов, и удалять её юнит нельзя.
         */
        try {
            $pullRequests = $client->pullRequests()->all($login, $code, [
                'state' => 'closed',
                'base' => $mainBranch,
                'head' => $login . ':' . $branch,
                'per_page' => 100,
            ]);

            foreach ($pullRequests as $pullRequest) {
                if (!empty($pullRequest['merged_at'])) {
                    return new SostoyanieVetki($branch, StatusVetki::MERGED, $unixtime);
                }
            }
        } catch (\Throwable) {
            // Не считаем ветку слитой, если PR API недоступен.
        }

        return new SostoyanieVetki($branch, StatusVetki::ACTIVE, $unixtime);
    }

    public function sozdatVetkuVHranilishe(Repo $repo, string $projectCode, string $nazvanieVetki, string $otVetki, string $token): void
    {
        $client = $this->githubClientFactory->makeClientWithToken($repo, $token);
        list($login, $code) = explode('/', $projectCode);

        $baseBranch = $client->repositories()->branches($login, $code, $otVetki);
        $sha = $baseBranch['commit']['sha'] ?? null;

        if (empty($sha)) {
            throw new \RuntimeException(sprintf(
                'Cannot resolve sha of base branch %s for project %s',
                $otVetki,
                $projectCode,
            ));
        }

        $client->gitData()->references()->create($login, $code, [
            'ref' => 'refs/heads/' . $nazvanieVetki,
            'sha' => $sha,
        ]);
    }

    public function getUrlForInitProject(Repo $repo, Project $project): string
    {
        $url = $repo->getCredentials()->url;
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (empty($scheme)) {
            $scheme = "";
        }
        $urlWithoutScheme = preg_replace("/https?:\/\//misu", "", $url);
        $storageUrl = $scheme.'://'.$repo->getCredentials()->token.'@'.$urlWithoutScheme;
        $projectUrl = $storageUrl . '/' . $project->getCode() . '.git';
        return $projectUrl;
    }

    public function canPush(Repo $repo, Project $project): bool
    {
        $client = $this->githubClientFactory->makeClient($repo);
        list($login, $code) = explode('/', $project->getCode());
        $data = $client->repositories()->show($login, $code);

        return PushPermission::izGithub($data);
    }

    public function canPushTokenom(string $token, Repo $repo, Project $project): bool
    {
        $client = $this->githubClientFactory->makeClientWithToken($repo, $token);
        list($login, $code) = explode('/', $project->getCode());
        $data = $client->repositories()->show($login, $code);

        return PushPermission::izGithub($data);
    }

    private function eto404(\Throwable $e): bool
    {
        return $e->getCode() === 404 || str_contains($e->getMessage(), '404');
    }

    private function izvlechUnixtime(?string ...$dates): ?int
    {
        foreach ($dates as $date) {
            if ($date === null || $date === '') {
                continue;
            }

            $unixtime = strtotime($date);
            if ($unixtime !== false) {
                return $unixtime;
            }
        }

        return null;
    }

    public function proveritToken(string $token, Repo $repo): bool
    {
        try {
            $client = $this->githubClientFactory->makeClientWithToken($repo, $token);
            $client->currentUser()->show();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }


    /**
     * @psalm-suppress InvalidReturnType
     * @psalm-suppress InvalidReturnStatement
     * @param Repo $repo
     * @param string $projectCode
     * @param string $branchName
     * @return string
     */
    public function poluchitKonfigIzHranilisha(Repo $repo, string $projectCode, string $branchName): string
    {
        $client = $this->githubClientFactory->makeClient($repo);
        list($login, $code) = explode('/', $projectCode);
        $config = $client->repository()->contents()->rawDownload($login, $code, 'unitman.yaml', $branchName);
        return $config;
    }

    public function poluchitProektiHranilisha(Repo $repo, ?string $query): array
    {
        $client = $this->githubClientFactory->makeClient($repo);
        return array_map(fn(array $item) => new ProektHranilisha($item['full_name'] ?? "", $item['name'] ?? ""),$client->currentUser()->repositories());
    }
}
