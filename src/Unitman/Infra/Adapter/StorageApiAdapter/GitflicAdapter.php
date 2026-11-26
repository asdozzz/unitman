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
 * GitFlic: публичный REST API.
 *
 * Для gitflic.ru запросы идут на https://api.gitflic.ru, для self-hosted —
 * на {host}/rest-api. Авторизация: Authorization: token <token>.
 *
 * Ограничение SaaS — 500 запросов в час, поэтому списки читаются страницами
 * и не должны опрашиваться слишком часто.
 */
final class GitflicAdapter implements \App\Unitman\Infra\Adapter\StorageApiAdapter
{
    private const SAAS_HOST = 'gitflic.ru';
    private const SAAS_URL = 'https://gitflic.ru';
    private const SAAS_API_URL = 'https://api.gitflic.ru';
    private const PAGE_SIZE = 50;
    private const MAX_PAGES = 100;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly \App\Unitman\Business\Port\UmeetShifrovatSekrety $shifr,
    )
    {
    }

    public function isSupport(Repo\RepoType $repoType): bool
    {
        return $repoType === Repo\RepoType::GITFLIC;
    }

    public function getRepoTypeModel(): RepoTypeList
    {
        return new RepoTypeList(Repo\RepoType::GITFLIC->value, Repo\RepoType::GITFLIC->name);
    }

    public function checkAccess(Repo $repo): CheckAccessResponse
    {
        try {
            $projects = $this->poluchitVseStranici($repo, '/project/my');

            if ($projects === []) {
                return CheckAccessResponse::fromError('Not found projects');
            }
        } catch (\Throwable $e) {
            return CheckAccessResponse::fromError($e->getMessage());
        }

        return CheckAccessResponse::success();
    }

    public function poluchitUrlHranilisha(?string $repoUrl): string
    {
        if ($repoUrl === null || trim($repoUrl) === '') {
            return self::SAAS_URL;
        }

        $url = rtrim(trim($repoUrl), '/');
        $url = preg_replace('~/rest-api$~i', '', $url) ?? $url;

        $host = strtolower(parse_url($url, PHP_URL_HOST) ?: '');
        if ($host === 'api.'.self::SAAS_HOST || $host === 'www.'.self::SAAS_HOST) {
            return self::SAAS_URL;
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new \Exception('repo.credentials.url_invalid');
        }

        return $url;
    }

    public function poluchitVetkiProekta(Repo $repo, string $projectCode, ?string $query): array
    {
        $branches = $this->poluchitVseStranici($repo, $this->repoPath($projectCode).'/branch');
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
        try {
            $branchData = $this->zapros($repo, 'GET', $this->repoPath($projectCode).'/branch', [
                'name' => $branch,
            ]);
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
            $branchData['lastCommit']['timestamp'] ?? null,
            $branchData['lastCommit']['committedAt'] ?? null,
            $branchData['lastCommit']['date'] ?? null,
        );

        if ($branch === $mainBranch) {
            return new SostoyanieVetki($branch, StatusVetki::ACTIVE, $unixtime);
        }

        if (($branchData['merged'] ?? false) === true) {
            return new SostoyanieVetki($branch, StatusVetki::MERGED, $unixtime);
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
            $this->repoPath($projectCode).'/branch',
            [],
            [
                'newBranch' => $nazvanieVetki,
                'originBranch' => $otVetki,
            ],
            $token,
        );
    }

    public function getUrlForInitProject(Repo $repo, Project $project): string
    {
        $url = $this->poluchitUrlHranilisha($repo->getCredentials()->url);
        $scheme = parse_url($url, PHP_URL_SCHEME) ?: 'https';
        $host = parse_url($url, PHP_URL_HOST) ?: '';

        if ($host === '') {
            throw new \RuntimeException('gitflic.url_invalid');
        }

        $port = parse_url($url, PHP_URL_PORT);
        $prefix = $scheme.'://'.$repo->rasshifrovatToken($this->shifr).'@'.$host
            .($port !== null && $port !== false ? ':'.$port : '');

        $path = trim(parse_url($url, PHP_URL_PATH) ?: '', '/');
        if ($path !== '') {
            $prefix .= '/'.$path;
        }

        return $prefix.'/project/'.ltrim($project->getCode(), '/').'.git';
    }

    public function canPush(Repo $repo, Project $project): bool
    {
        return PushPermission::izGitflic(
            $this->zapros($repo, 'GET', $this->repoPath($project->getCode())),
        );
    }

    public function canPushTokenom(string $token, Repo $repo, Project $project): bool
    {
        return PushPermission::izGitflic(
            $this->zapros($repo, 'GET', $this->repoPath($project->getCode()), [], null, $token),
        );
    }

    public function proveritToken(string $token, Repo $repo): bool
    {
        try {
            $this->zapros($repo, 'GET', '/project/my', ['size' => 1], null, $token);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function poluchitKonfigIzHranilisha(Repo $repo, string $projectCode, string $branchName): string
    {
        $branchData = $this->zapros($repo, 'GET', $this->repoPath($projectCode).'/branch', [
            'name' => $branchName,
        ]);

        $commitHash = $this->izvlechCommitHash($branchData);
        if ($commitHash === null) {
            throw new \RuntimeException('gitflic.commit_not_found');
        }

        $response = $this->httpClient->request(
            'GET',
            $this->apiBase($repo).$this->repoPath($projectCode).'/blob',
            [
                'headers' => $this->headers($repo->rasshifrovatToken($this->shifr)),
                'query' => [
                    'commitHash' => $commitHash,
                    'file' => 'unitman.yaml',
                ],
            ],
        );

        if ($response->getStatusCode() >= 400) {
            throw new \RuntimeException('gitflic.config_not_found', $response->getStatusCode());
        }

        return $response->getContent(false);
    }

    public function poluchitProektiHranilisha(Repo $repo, ?string $query): array
    {
        $query = $query !== null ? trim($query) : '';
        $parameters = $query !== '' ? ['q' => $query] : [];
        $projects = $this->poluchitVseStranici($repo, '/project/my', $parameters);
        $result = [];

        foreach ($projects as $project) {
            $owner = (string)($project['ownerAlias'] ?? ($project['owner']['alias'] ?? ''));
            $alias = (string)($project['alias'] ?? ($project['projectAlias'] ?? ''));
            $code = trim($owner.'/'.$alias, '/');

            if ($code === '' || $code === '/') {
                continue;
            }

            $name = (string)($project['title'] ?? ($project['name'] ?? $code));
            $result[] = new ProektHranilisha($code, $name);
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
            'headers' => $this->headers($token ?? $repo->rasshifrovatToken($this->shifr)),
            'query' => $query,
        ];

        if ($json !== null) {
            $options['json'] = $json;
        }

        $response = $this->httpClient->request($method, $this->apiBase($repo).$path, $options);
        $status = $response->getStatusCode();

        if ($status >= 400) {
            throw new \RuntimeException('gitflic.request_failed', $status);
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
            /** @var mixed $data */
            $data = $this->zapros($repo, 'GET', $path, $query + [
                'size' => self::PAGE_SIZE,
                'page' => $page,
            ]);

            /** @var array<int, mixed> $items */
            $items = is_array($data) && isset($data['content']) && is_array($data['content'])
                ? $data['content']
                : (is_array($data) ? $data : []);

            if ($items === []) {
                break;
            }

            $added = 0;
            foreach ($items as $item) {
                if (is_array($item)) {
                    /** @var array<string, mixed> $item */
                    $result[] = $item;
                    $added++;
                }
            }

            if ($added < self::PAGE_SIZE) {
                break;
            }
        }

        return $result;
    }

    /**
     * @return array<string, string>
     */
    private function headers(string $token): array
    {
        return [
            'Authorization' => 'token '.$token,
            'Accept' => 'application/json',
        ];
    }

    private function apiBase(Repo $repo): string
    {
        $url = $this->poluchitUrlHranilisha($repo->getCredentials()->url);
        $host = strtolower(parse_url($url, PHP_URL_HOST) ?: '');

        if ($host === self::SAAS_HOST || $host === 'www.'.self::SAAS_HOST) {
            return self::SAAS_API_URL;
        }

        return $url.'/rest-api';
    }

    private function repoPath(string $projectCode): string
    {
        $parts = explode('/', trim($projectCode, '/'), 2);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new \InvalidArgumentException('gitflic.project_code_invalid');
        }

        return '/project/'.rawurlencode($parts[0]).'/'.rawurlencode($parts[1]);
    }

    private function izvlechCommitHash(mixed $branchData): ?string
    {
        if (!is_array($branchData)) {
            return null;
        }

        $lastCommit = $branchData['lastCommit'] ?? null;
        if (!is_array($lastCommit)) {
            return null;
        }

        foreach (['hash', 'commitHash', 'id', 'sha'] as $key) {
            $value = $lastCommit[$key] ?? null;
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
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
