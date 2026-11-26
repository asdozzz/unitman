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
use App\Unitman\Business\Port\UmeetShifrovatSekrety;
use App\Unitman\Infra\Adapter\StorageApiAdapter\GitlabAdapter\GitlabClientFactory;
use Gitlab\ResultPager;

final class GitlabAdapter implements \App\Unitman\Infra\Adapter\StorageApiAdapter
{
    public function __construct(
        private GitlabClientFactory $clientFactory,
        private UmeetShifrovatSekrety $shifr,
    )
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
            if (empty($datetime1)) {
                $datetime1 = 0;
            }
            $datetime2 = strtotime($b['commit']['created_at']);
            if (empty($datetime2)) {
                $datetime2 = 0;
            }
            return $datetime2 - $datetime1;
        });

        return array_map(fn(array $branch) => new VetkaProekta($branch['name']), $branches);
    }

    public function poluchitSostoyanieVetki(
        Repo $repo,
        string $projectCode,
        string $branch,
        string $mainBranch,
    ): SostoyanieVetki {
        $client = $this->clientFactory->makeClient($repo);

        try {
            $branchData = $client->repositories()->branch($projectCode, $branch);
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
            $branchData['commit']['committed_date'] ?? null,
            $branchData['commit']['created_at'] ?? null,
        );

        if ($branch === $mainBranch) {
            return new SostoyanieVetki($branch, StatusVetki::ACTIVE, $unixtime);
        }

        /*
         * Слияние подтверждаем только merged merge request. Это надёжнее
         * сравнения коммитов: ветка без уникальных коммитов ещё не значит,
         * что её MR был слит.
         */
        try {
            $mergeRequests = $client->mergeRequests()->all($projectCode, [
                'state' => 'merged',
                'source_branch' => $branch,
                'target_branch' => $mainBranch,
                'per_page' => 100,
            ]);

            if (!empty($mergeRequests)) {
                return new SostoyanieVetki($branch, StatusVetki::MERGED, $unixtime);
            }
        } catch (\Throwable) {
            // Не считаем ветку слитой, если MR API недоступен.
        }

        return new SostoyanieVetki($branch, StatusVetki::ACTIVE, $unixtime);
    }

    public function sozdatVetkuVHranilishe(Repo $repo, string $projectCode, string $nazvanieVetki, string $otVetki, string $token): void
    {
        $client = $this->clientFactory->makeClientWithToken($repo, $token);
        $client->repositories()->createBranch($projectCode, $nazvanieVetki, $otVetki);
    }

    public function getUrlForInitProject(Repo $repo, Project $project): string
    {
        $url = $repo->getCredentials()->url;
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (empty($scheme)) {
            $scheme = "";
        }
        $urlWithoutScheme = preg_replace("/https?:\/\//misu", "", $url);

        // URL без userinfo: токен не должен попадать в Temporal/read-модели.
        // Git получает его через broker + askpass.
        return $scheme.'://'.$urlWithoutScheme.'/'.$project->getCode().'.git';
    }

    public function canPush(Repo $repo, Project $project): bool
    {
        $client = $this->clientFactory->makeClient($repo);
        $data = $client->projects()->show($project->getCode());

        return PushPermission::izGitlab($data);
    }

    public function canPushTokenom(string $token, Repo $repo, Project $project): bool
    {
        $client = $this->clientFactory->makeClientWithToken($repo, $token);
        $data = $client->projects()->show($project->getCode());

        return PushPermission::izGitlab($data);
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
            $client = $this->clientFactory->makeClientWithToken($repo, $token);
            $client->users()->me();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function poluchitKonfigIzHranilisha(Repo $repo, string $projectCode, string $branchName): string
    {
        $client = $this->clientFactory->makeClient($repo);
        return $client->repositoryFiles()->getRawFile($projectCode, 'unitman.yaml', $branchName);
    }

    public function poluchitProektiHranilisha(Repo $repo, ?string $query): array
    {
        $client = $this->clientFactory->makeClient($repo);

        $parameters = [
            'per_page' => 100,
            'simple' => true,
            'membership' => true,
        ];

        // Поиск на фронте локальный, поэтому search передаём только когда он
        // реально задан: пустая строка в search ломает часть версий GitLab API.
        if ($query !== null && $query !== '') {
            $parameters['search'] = $query;
        }

        $all = (new ResultPager($client))->fetchAll($client->projects(), 'all', $parameters);

        return array_map(fn(array $item) => new ProektHranilisha($item['path_with_namespace'] ?? "", $item['name_with_namespace'] ?? ""), $all);
    }
}
