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
 * GitVerse: публичный REST API.
 *
 * База SaaS — https://api.gitverse.ru, авторизация Bearer, обязательный
 * Accept-заголовок версии API. Содержимое файлов приходит в Base64.
 */
final class GitverseAdapter implements \App\Unitman\Infra\Adapter\StorageApiAdapter
{
    private const SAAS_URL = 'https://gitverse.ru';
    private const SAAS_API_URL = 'https://api.gitverse.ru';
    private const ACCEPT = 'application/vnd.gitverse.object+json;version=1';
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
        return $repoType === Repo\RepoType::GITVERSE;
    }

    public function getRepoTypeModel(): RepoTypeList
    {
        return new RepoTypeList(Repo\RepoType::GITVERSE->value, Repo\RepoType::GITVERSE->name);
    }

    public function checkAccess(Repo $repo): CheckAccessResponse
    {
        try {
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
            return self::SAAS_URL;
        }

        $url = rtrim(trim($repoUrl), '/');
        $url = preg_replace('~/api$~i', '', $url) ?? $url;

        $host = strtolower(parse_url($url, PHP_URL_HOST) ?: '');
        if ($host === 'api.gitverse.ru' || $host === 'www.gitverse.ru') {
            return self::SAAS_URL;
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new \Exception('repo.credentials.url_invalid');
        }

        return $url;
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
            $branchData['commit']['created_at'] ?? null,
            $branchData['commit']['committer']['date'] ?? null,
            $branchData['commit']['author']['date'] ?? null,
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
                $head = (string)(
                    $pull['head']['ref']
                    ?? $pull['head']['branch']
                    ?? $pull['source']['branch']['name']
                    ?? ''
                );
                $base = (string)(
                    $pull['base']['ref']
                    ?? $pull['base']['branch']
                    ?? $pull['target']['branch']['name']
                    ?? ''
                );

                if ($head !== $branch || $base !== $mainBranch) {
                    continue;
                }

                if (!empty($pull['merged_at'])
                    || ($pull['merged'] ?? false) === true
                    || strtoupper((string)($pull['state'] ?? '')) === 'MERGED') {
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
            $this->repoPath($projectCode).'/branches/'.rawurlencode($otVetki),
            [],
            null,
            $token,
        );

        $sha = $this->izvlechSha($baseBranch);
        if ($sha === null) {
            throw new \RuntimeException(sprintf(
                'Cannot resolve sha of base branch %s for project %s',
                $otVetki,
                $projectCode,
            ));
        }

        $this->zapros(
            $repo,
            'POST',
            $this->repoPath($projectCode).'/git/refs',
            [],
            [
                'ref' => 'refs/heads/'.$nazvanieVetki,
                'sha' => $sha,
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
            throw new \RuntimeException('gitverse.url_invalid');
        }

        $port = parse_url($url, PHP_URL_PORT);
        $prefix = $scheme.'://'.$repo->rasshifrovatToken($this->shifr).'@'.$host
            .($port !== null && $port !== false ? ':'.$port : '');

        $path = trim(parse_url($url, PHP_URL_PATH) ?: '', '/');
        if ($path !== '') {
            $prefix .= '/'.$path;
        }

        return $prefix.'/'.ltrim($project->getCode(), '/').'.git';
    }

    public function canPush(Repo $repo, Project $project): bool
    {
        return PushPermission::izGitverse(
            $this->zapros($repo, 'GET', $this->repoPath($project->getCode())),
        );
    }

    public function canPushTokenom(string $token, Repo $repo, Project $project): bool
    {
        return PushPermission::izGitverse(
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
        $data = $this->zapros(
            $repo,
            'GET',
            $this->repoPath($projectCode).'/contents/unitman.yaml',
            ['ref' => $branchName],
        );

        if (is_string($data)) {
            return $data;
        }

        if (!is_array($data) || !is_string($data['content'] ?? null)) {
            throw new \RuntimeException('gitverse.config_not_found');
        }

        $content = (string)$data['content'];
        $encoding = strtolower((string)($data['encoding'] ?? 'base64'));

        if ($encoding !== 'base64') {
            return $content;
        }

        $decoded = base64_decode(str_replace(["\n", "\r"], '', $content), true);
        if ($decoded === false) {
            throw new \RuntimeException('gitverse.config_decode_failed');
        }

        return $decoded;
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
                'Authorization' => 'Bearer '.($token ?? $repo->rasshifrovatToken($this->shifr)),
                'Accept' => self::ACCEPT,
            ],
            'query' => $query,
        ];

        if ($json !== null) {
            $options['json'] = $json;
        }

        $response = $this->httpClient->request($method, $this->apiBase($repo).$path, $options);
        $status = $response->getStatusCode();

        if ($status >= 400) {
            throw new \RuntimeException('gitverse.request_failed', $status);
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
            $items = $this->zapros($repo, 'GET', $path, $query + [
                'per_page' => self::PAGE_SIZE,
                'page' => $page,
            ]);

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
        $url = $this->poluchitUrlHranilisha($repo->getCredentials()->url);
        $host = strtolower(parse_url($url, PHP_URL_HOST) ?: '');

        if ($host === 'gitverse.ru' || $host === 'www.gitverse.ru') {
            return self::SAAS_API_URL;
        }

        return $url;
    }

    private function repoPath(string $projectCode): string
    {
        $parts = explode('/', trim($projectCode, '/'), 2);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new \InvalidArgumentException('gitverse.project_code_invalid');
        }

        return '/repos/'.rawurlencode($parts[0]).'/'.rawurlencode($parts[1]);
    }

    private function izvlechSha(mixed $branchData): ?string
    {
        if (!is_array($branchData)) {
            return null;
        }

        $commit = $branchData['commit'] ?? null;
        if (!is_array($commit)) {
            return null;
        }

        foreach (['sha', 'id', 'hash'] as $key) {
            $value = $commit[$key] ?? null;
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
