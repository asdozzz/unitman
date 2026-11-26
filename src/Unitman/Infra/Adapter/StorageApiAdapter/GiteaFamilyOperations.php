<?php

declare(strict_types=1);

namespace App\Unitman\Infra\Adapter\StorageApiAdapter;

use App\Unitman\Business\Model\Project;
use App\Unitman\Business\Model\Project\StatusVetki;
use App\Unitman\Business\Model\Repo;
use App\Unitman\Business\Model\RepoAdapter\CheckAccessResponse;
use App\Unitman\Business\ReadModel\ProektHranilisha;
use App\Unitman\Business\ReadModel\SostoyanieVetki;
use App\Unitman\Business\ReadModel\VetkaProekta;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Общая реализация StorageApiAdapter для Gitea-совместимых хостингов.
 *
 * Gitea, Forgejo и Codeberg используют один и тот же API v1, поэтому
 * различаются только типом репозитория, базовым URL и заголовками вебхуков.
 */
trait GiteaFamilyOperations
{
    private const PAGE_SIZE = 50;
    private const MAX_PAGES = 100;

    public function __construct(private readonly HttpClientInterface $httpClient)
    {
    }

    public function checkAccess(Repo $repo): CheckAccessResponse
    {
        try {
            $this->zapros($repo, 'GET', '/user');
            $repositories = $this->poluchitVseStranici($repo, '/user/repos');

            if ($repositories === []) {
                return CheckAccessResponse::fromError('Not found repositories');
            }
        } catch (\Throwable $e) {
            return CheckAccessResponse::fromError($e->getMessage());
        }

        return CheckAccessResponse::success();
    }

    public function poluchitUrlHranilisha(?string $repoUrl): string
    {
        if ($repoUrl === null || trim($repoUrl) === '') {
            throw new \Exception('repo.url_is_empty');
        }

        return $this->normalizovatUrl($repoUrl);
    }

    public function poluchitVetkiProekta(Repo $repo, string $projectCode, ?string $query): array
    {
        $branches = $this->poluchitVseStranici($repo, $this->repoPath($projectCode).'/branches');
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
        $path = $this->repoPath($projectCode).'/branches/'.rawurlencode($branch);

        try {
            $branchData = $this->zapros($repo, 'GET', $path);
        } catch (\Throwable $e) {
            return new SostoyanieVetki(
                $branch,
                $this->eto404($e) ? StatusVetki::DELETED : StatusVetki::UNKNOWN,
            );
        }

        if (!is_array($branchData) || ($branchData['name'] ?? null) !== $branch) {
            return new SostoyanieVetki($branch, StatusVetki::DELETED);
        }

        $unixtime = $this->izvlechUnixtime(
            $branchData['commit']['timestamp'] ?? null,
            $branchData['commit']['created'] ?? null,
        );

        if ($branch === $mainBranch) {
            return new SostoyanieVetki($branch, StatusVetki::ACTIVE, $unixtime);
        }

        try {
            $pulls = $this->poluchitVseStranici(
                $repo,
                $this->repoPath($projectCode).'/pulls',
                ['state' => 'closed'],
            );

            foreach ($pulls as $pull) {
                if (($pull['head']['ref'] ?? null) !== $branch) {
                    continue;
                }

                if (($pull['base']['ref'] ?? null) !== $mainBranch) {
                    continue;
                }

                if (!empty($pull['merged_at']) || ($pull['merged'] ?? false) === true) {
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
        $this->zapros(
            $repo,
            'POST',
            $this->repoPath($projectCode).'/branches',
            [],
            [
                'new_branch_name' => $nazvanieVetki,
                'old_branch_name' => $otVetki,
            ],
            $token,
        );
    }

    public function getUrlForInitProject(Repo $repo, Project $project): string
    {
        $url = $this->normalizovatUrl($repo->getCredentials()->url);
        $scheme = parse_url($url, PHP_URL_SCHEME) ?: 'https';
        $host = parse_url($url, PHP_URL_HOST) ?: '';

        if ($host === '') {
            throw new \RuntimeException('gitea.url_invalid');
        }

        $port = parse_url($url, PHP_URL_PORT);
        $prefix = $scheme.'://'.$repo->getCredentials()->token.'@'.$host
            .($port !== null && $port !== false ? ':'.$port : '');

        $path = trim(parse_url($url, PHP_URL_PATH) ?: '', '/');
        if ($path !== '') {
            $prefix .= '/'.$path;
        }

        return $prefix.'/'.ltrim($project->getCode(), '/').'.git';
    }

    public function canPush(Repo $repo, Project $project): bool
    {
        return PushPermission::izGitea(
            $this->zapros($repo, 'GET', $this->repoPath($project->getCode())),
        );
    }

    public function canPushTokenom(string $token, Repo $repo, Project $project): bool
    {
        return PushPermission::izGitea(
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
        $response = $this->httpClient->request(
            'GET',
            $this->apiBase($repo).$this->repoPath($projectCode).'/raw/unitman.yaml',
            [
                'headers' => ['Authorization' => 'token '.$repo->getCredentials()->token],
                'query' => ['ref' => $branchName],
            ],
        );

        if ($response->getStatusCode() >= 400) {
            throw new \RuntimeException('gitea.config_not_found', $response->getStatusCode());
        }

        return $response->getContent(false);
    }

    public function poluchitProektiHranilisha(Repo $repo, ?string $query): array
    {
        $query = $query !== null ? trim($query) : '';
        $result = [];

        foreach ($this->poluchitVseStranici($repo, '/user/repos') as $item) {
            $fullName = (string)($item['full_name'] ?? '');

            if ($fullName === '') {
                continue;
            }

            if ($query !== '' && !str_contains($fullName, $query)) {
                continue;
            }

            $result[] = new ProektHranilisha($fullName, $fullName);
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
    ): mixed {
        $options = [
            'headers' => [
                'Authorization' => 'token '.($token ?? $repo->getCredentials()->token),
                'Accept' => 'application/json',
            ],
            'query' => $query,
        ];

        if ($json !== null) {
            $options['json'] = $json;
        }

        $response = $this->httpClient->request($method, $this->apiBase($repo).$path, $options);
        $status = $response->getStatusCode();

        if ($status >= 400) {
            throw new \RuntimeException('gitea.request_failed', $status);
        }

        return $response->toArray(false);
    }

    /**
     * @param array<string, scalar|null> $query
     * @return array<int, array<string, mixed>>
     */
    private function poluchitVseStranici(Repo $repo, string $path, array $query = []): array
    {
        $result = [];

        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            /** @var mixed $items */
            $items = $this->zapros(
                $repo,
                'GET',
                $path,
                $query + ['limit' => self::PAGE_SIZE, 'page' => $page],
            );

            if (!is_array($items) || $items === []) {
                break;
            }

            foreach ($items as $item) {
                if (is_array($item)) {
                    /** @var array<string, mixed> $item */
                    $result[] = $item;
                }
            }

            if (count($items) < self::PAGE_SIZE) {
                break;
            }
        }

        return $result;
    }

    private function apiBase(Repo $repo): string
    {
        return $this->normalizovatUrl($repo->getCredentials()->url).'/api/v1';
    }

    private function repoPath(string $projectCode): string
    {
        $parts = explode('/', trim($projectCode, '/'), 2);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new \InvalidArgumentException('gitea.project_code_invalid');
        }

        return '/repos/'.rawurlencode($parts[0]).'/'.rawurlencode($parts[1]);
    }

    private function normalizovatUrl(string $url): string
    {
        $url = rtrim(trim($url), '/');
        $url = preg_replace('~/api/v1$~i', '', $url) ?? $url;

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new \Exception('repo.credentials.url_invalid');
        }

        return $url;
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
