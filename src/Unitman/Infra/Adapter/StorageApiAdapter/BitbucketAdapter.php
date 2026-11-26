<?php

declare(strict_types=1);

namespace App\Unitman\Infra\Adapter\StorageApiAdapter;

use App\Unitman\Business\Model\Project;
use App\Unitman\Business\Model\Project\StatusVetki;
use App\Unitman\Business\Model\Repo;
use App\Unitman\Business\Model\RepoAdapter\CheckAccessResponse;
use App\Unitman\Business\ReadModel\ProektHranilisha;
use App\Unitman\Business\ReadModel\RepoTypeList;
use App\Unitman\Business\ReadModel\SostoyanieVetki;
use App\Unitman\Business\ReadModel\VetkaProekta;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Bitbucket Cloud: REST API 2.0.
 *
 * Авторизация — Bearer-токен (repository/workspace access token). Клон
 * выполняется от имени x-token-auth. Для списка проектов сначала читаются
 * workspace'ы пользователя, затем репозитории каждого workspace.
 */
final class BitbucketAdapter implements \App\Unitman\Infra\Adapter\StorageApiAdapter
{
    private const REPO_URL = 'https://bitbucket.org';
    private const API_URL = 'https://api.bitbucket.org/2.0';
    private const PAGE_LENGTH = 50;

    public function __construct(private readonly HttpClientInterface $httpClient)
    {
    }

    public function isSupport(Repo\RepoType $repoType): bool
    {
        return $repoType === Repo\RepoType::BITBUCKET;
    }

    public function getRepoTypeModel(): RepoTypeList
    {
        return new RepoTypeList(Repo\RepoType::BITBUCKET->value, Repo\RepoType::BITBUCKET->name);
    }

    public function checkAccess(Repo $repo): CheckAccessResponse
    {
        try {
            $this->zapros($repo, 'GET', '/user');
            $workspaces = $this->poluchitVseZnacheniya($repo, '/user/workspaces');

            if ($workspaces === []) {
                return CheckAccessResponse::fromError('Not found workspaces');
            }
        } catch (\Throwable $e) {
            return CheckAccessResponse::fromError($e->getMessage());
        }

        return CheckAccessResponse::success();
    }

    public function poluchitUrlHranilisha(?string $repoUrl): string
    {
        return self::REPO_URL;
    }

    public function poluchitVetkiProekta(Repo $repo, string $projectCode, ?string $query): array
    {
        $path = $this->repoPath($projectCode).'/refs/branches';
        $branches = $this->poluchitVseZnacheniya($repo, $path);
        $query = $query !== null ? trim($query) : '';
        $result = [];

        foreach ($branches as $branch) {
            $name = (string)($branch['name'] ?? '');

            if ($name === '') {
                continue;
            }

            if ($query !== '' && !str_contains($name, $query)) {
                continue;
            }

            $result[] = new VetkaProekta($name);
        }

        return $result;
    }

    public function poluchitSostoyanieVetki(
        Repo $repo,
        string $projectCode,
        string $branch,
        string $mainBranch,
    ): SostoyanieVetki {
        $path = $this->repoPath($projectCode).'/refs/branches/'.rawurlencode($branch);

        try {
            $branchData = $this->zapros($repo, 'GET', $path);
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
            $branchData['target']['date'] ?? null,
            $branchData['target']['committer']['date'] ?? null,
        );

        if ($branch === $mainBranch) {
            return new SostoyanieVetki($branch, StatusVetki::ACTIVE, $unixtime);
        }

        try {
            $pullRequests = $this->poluchitVseZnacheniya(
                $repo,
                $this->repoPath($projectCode).'/pullrequests',
                ['state' => 'MERGED'],
            );

            foreach ($pullRequests as $pullRequest) {
                if (($pullRequest['source']['branch']['name'] ?? null) !== $branch) {
                    continue;
                }

                if (($pullRequest['destination']['branch']['name'] ?? null) !== $mainBranch) {
                    continue;
                }

                if (($pullRequest['state'] ?? null) === 'MERGED' || !empty($pullRequest['merged_on'])) {
                    return new SostoyanieVetki($branch, StatusVetki::MERGED, $unixtime);
                }
            }
        } catch (\Throwable) {
            // Не считаем ветку слитой, если API pull requests недоступен.
        }

        return new SostoyanieVetki($branch, StatusVetki::ACTIVE, $unixtime);
    }

    public function sozdatVetkuVHranilishe(
        Repo $repo,
        string $projectCode,
        string $nazvanieVetki,
        string $otVetki,
        string $token,
    ): void {
        $baseBranch = $this->zapros(
            $repo,
            'GET',
            $this->repoPath($projectCode).'/refs/branches/'.rawurlencode($otVetki),
            [],
            null,
            $token,
        );

        $hash = $baseBranch['target']['hash'] ?? null;
        if (!is_string($hash) || $hash === '') {
            throw new \RuntimeException(sprintf(
                'Cannot resolve hash of base branch %s for project %s',
                $otVetki,
                $projectCode,
            ));
        }

        $this->zapros(
            $repo,
            'POST',
            $this->repoPath($projectCode).'/refs/branches',
            [],
            [
                'name' => $nazvanieVetki,
                'target' => ['hash' => $hash],
            ],
            $token,
        );
    }

    public function getUrlForInitProject(Repo $repo, Project $project): string
    {
        return 'https://x-token-auth:'.$repo->getCredentials()->token.'@bitbucket.org/'
            .ltrim($project->getCode(), '/').'.git';
    }

    public function canPush(Repo $repo, Project $project): bool
    {
        return PushPermission::izBitbucket(
            $this->zapros($repo, 'GET', $this->repoPath($project->getCode())),
        );
    }

    public function canPushTokenom(string $token, Repo $repo, Project $project): bool
    {
        return PushPermission::izBitbucket(
            $this->zapros($repo, 'GET', $this->repoPath($project->getCode()), [], null, $token),
        );
    }

    public function proveritToken(string $token, Repo $repo): bool
    {
        try {
            $this->zapros($repo, 'GET', '/user', [], null, $token);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function poluchitKonfigIzHranilisha(Repo $repo, string $projectCode, string $branchName): string
    {
        $url = self::API_URL.$this->repoPath($projectCode)
            .'/src/'.rawurlencode($branchName).'/unitman.yaml';

        $response = $this->httpClient->request('GET', $url, [
            'headers' => ['Authorization' => 'Bearer '.$repo->getCredentials()->token],
        ]);

        if ($response->getStatusCode() >= 400) {
            throw new \RuntimeException('bitbucket.config_not_found', $response->getStatusCode());
        }

        return $response->getContent(false);
    }

    public function poluchitProektiHranilisha(Repo $repo, ?string $query): array
    {
        $query = $query !== null ? trim($query) : '';
        $result = [];
        $workspaces = $this->poluchitVseZnacheniya($repo, '/user/workspaces');

        foreach ($workspaces as $workspace) {
            $slug = (string)($workspace['slug'] ?? '');

            if ($slug === '') {
                continue;
            }

            $repositories = $this->poluchitVseZnacheniya(
                $repo,
                '/repositories/'.rawurlencode($slug),
            );

            foreach ($repositories as $item) {
                $fullName = (string)($item['full_name'] ?? '');

                if ($fullName === '') {
                    continue;
                }

                if ($query !== '' && !str_contains($fullName, $query)) {
                    continue;
                }

                $result[] = new ProektHranilisha($fullName, $fullName);
            }
        }

        return $result;
    }

    /**
     * @param array<string, scalar|null> $query
     * @param array<string, mixed>|null $json
     */
    private function zapros(
        Repo $repo,
        string $method,
        string $path,
        array $query = [],
        ?array $json = null,
        ?string $token = null,
    ): array {
        return $this->zaprosUrl(
            $repo,
            $method,
            self::API_URL.$path,
            $query,
            $json,
            $token,
        );
    }

    /**
     * @param array<string, scalar|null> $query
     * @param array<string, mixed>|null $json
     * @return array<string, mixed>
     */
    private function zaprosUrl(
        Repo $repo,
        string $method,
        string $url,
        array $query = [],
        ?array $json = null,
        ?string $token = null,
    ): array {
        $options = [
            'headers' => [
                'Authorization' => 'Bearer '.($token ?? $repo->getCredentials()->token),
                'Accept' => 'application/json',
            ],
            'query' => $query,
        ];

        if ($json !== null) {
            $options['json'] = $json;
        }

        $response = $this->httpClient->request($method, $url, $options);
        $status = $response->getStatusCode();

        if ($status >= 400) {
            throw new \RuntimeException('bitbucket.request_failed', $status);
        }

        return $response->toArray(false);
    }

    /**
     * @param array<string, scalar|null> $query
     * @return array<int, array<string, mixed>>
     */
    private function poluchitVseZnacheniya(Repo $repo, string $path, array $query = []): array
    {
        $result = [];
        $url = self::API_URL.$path;
        $first = true;

        while ($url !== null) {
            $parameters = $first ? $query + ['pagelen' => self::PAGE_LENGTH] : [];
            $first = false;

            $data = $this->zaprosUrl($repo, 'GET', $url, $parameters);
            /** @var mixed $values */
            $values = $data['values'] ?? [];

            if (!is_array($values) || $values === []) {
                break;
            }

            foreach ($values as $value) {
                if (is_array($value)) {
                    /** @var array<string, mixed> $value */
                    $result[] = $value;
                }
            }

            /** @var mixed $next */
            $next = $data['next'] ?? null;
            $url = is_string($next) && $next !== '' ? $next : null;
        }

        return $result;
    }

    private function repoPath(string $projectCode): string
    {
        $parts = explode('/', trim($projectCode, '/'), 2);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new \InvalidArgumentException('bitbucket.project_code_invalid');
        }

        return '/repositories/'.rawurlencode($parts[0]).'/'.rawurlencode($parts[1]);
    }

    private function eto404(\Throwable $e): bool
    {
        return $e->getCode() === 404 || str_contains($e->getMessage(), '404');
    }

    private function izvlechUnixtime(mixed ...$dates): ?int
    {
        foreach ($dates as $date) {
            if (!is_string($date) || $date === '') {
                continue;
            }

            $unixtime = strtotime($date);
            if ($unixtime !== false) {
                return $unixtime;
            }
        }

        return null;
    }
}
