<?php

namespace App\Unitman\Infra\Jobs\SobitiyaIzHranilisha\StorageTypeAdapter;

use App\Unitman\Business\Model\Repo\RepoType;
use App\Unitman\Business\Model\SobitieIzHranilisha\DannieSobitiya;
use App\Unitman\Business\Model\SobitieIzHranilisha\SobitiePayload;
use App\Unitman\Business\Model\SobitieIzHranilisha\TipSobitiya;

final class GithubAdapter implements StorageTypeAdapter
{
    function poluchitDannieSobitiya(SobitiePayload $payload, string $mainBranch): DannieSobitiya
    {
        $data = $payload->data;

        if ($this->esliPullRequest($data)) {
            return $this->danniePullRequesta($data);
        }

        if (!empty($data['created'])) {
            $type = TipSobitiya::VETKA_SOZDANA;
        } else {
            $type = !empty($data['deleted']) ? TipSobitiya::VETKA_UDALENA : TipSobitiya::KOD_OBNOVLEN;
        }

        $vetka = $this->normalizovatVetku((string)($data['ref'] ?? ''));

        if ($type === TipSobitiya::VETKA_UDALENA || $type === TipSobitiya::VETKA_SOZDANA) {
            return new DannieSobitiya($type, $vetka, time());
        }

        $unixtime = $this->unixtimePoslednegoKommita($data);

        /*
         * GitHub после мерджа PR присылает push в основную ветку. Если в
         * коммитах есть merge-коммит PR, считаем событие слиянием ветки юнита.
         */
        if ($vetka === $mainBranch) {
            $vetkaIstochnika = $this->vetkaIstochnikaIzKommitov($data, $mainBranch);

            if ($vetkaIstochnika !== null) {
                return new DannieSobitiya(TipSobitiya::VETKA_SMERZHENA, $vetkaIstochnika, $unixtime, $vetka);
            }
        }

        return new DannieSobitiya($type, $vetka, $unixtime);
    }

    public function isSupport(RepoType $repoType): bool
    {
        return $repoType === RepoType::GITHUB;
    }

    public function esliValidnoeSobitie(SobitiePayload $payload): bool
    {
        $data = $payload->data;

        if ($this->esliPullRequest($data)) {
            return $this->esliZavershenniPullRequest($data);
        }

        if (strpos((string)($data['ref'] ?? ''), 'refs/tags') !== false) {
            return false;
        }

        return !empty($data['ref']) && (!empty($data['commits']) || !empty($data['deleted']) || !empty($data['created']));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function esliPullRequest(array $data): bool
    {
        return isset($data['pull_request']) && is_array($data['pull_request']);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function esliZavershenniPullRequest(array $data): bool
    {
        $pullRequest = $data['pull_request'] ?? null;

        return is_array($pullRequest)
            && ($data['action'] ?? null) === 'closed'
            && !empty($pullRequest['merged']);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function danniePullRequesta(array $data): DannieSobitiya
    {
        /** @var array<string, mixed> $pullRequest */
        $pullRequest = $data['pull_request'];

        $head = is_array($pullRequest['head'] ?? null) ? $pullRequest['head'] : [];
        $base = is_array($pullRequest['base'] ?? null) ? $pullRequest['base'] : [];

        $istochnik = $this->normalizovatVetku((string)($head['ref'] ?? ''));
        $cel = $this->normalizovatVetku((string)($base['ref'] ?? ''));

        return new DannieSobitiya(
            TipSobitiya::VETKA_SMERZHENA,
            $istochnik,
            $this->unixtimeIzStroki($pullRequest['merged_at'] ?? $pullRequest['updated_at'] ?? null),
            $cel,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function vetkaIstochnikaIzKommitov(array $data, string $mainBranch): ?string
    {
        $commits = $data['commits'] ?? null;

        if (!is_array($commits)) {
            return null;
        }

        foreach (array_reverse($commits) as $commit) {
            if (!is_array($commit) || !isset($commit['message'])) {
                continue;
            }

            $message = (string)$commit['message'];

            /* Формат merge-коммита GitHub: "Merge pull request #12 from org/feature/x". */
            if (preg_match('/^Merge pull request #\d+ from [^\/\s]+\/(.+)$/m', $message, $matches) === 1) {
                $source = trim($matches[1]);

                if ($source !== '' && $source !== $mainBranch) {
                    return $source;
                }
            }

            /* Локальный мердж: "Merge branch 'feature/x' into main". */
            if (preg_match('/^Merge branch \'([^\']+)\' into [\'"]?([^\'"\r\n]+)[\'"]?$/m', $message, $matches) === 1) {
                $target = trim($matches[2]);
                $source = trim($matches[1]);

                if ($target === $mainBranch && $source !== '' && $source !== $mainBranch) {
                    return $source;
                }
            }
        }

        return null;
    }

    private function normalizovatVetku(string $vetka): string
    {
        $vetka = str_replace('refs/heads/', '', $vetka);

        return str_replace('refs/', '', $vetka);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function unixtimePoslednegoKommita(array $data): int
    {
        $commits = $data['commits'] ?? null;

        if (!is_array($commits) || $commits === []) {
            throw new \Exception('sobitie_iz_hranilisha.commits_not_found');
        }

        $lastCommit = end($commits);

        if (!is_array($lastCommit) || !isset($lastCommit['timestamp'])) {
            throw new \Exception('sobitie_iz_hranilisha.commits_not_found');
        }

        return (new \DateTimeImmutable((string)$lastCommit['timestamp']))->getTimestamp();
    }

    private function unixtimeIzStroki(mixed $value): int
    {
        if (!is_string($value) || trim($value) === '') {
            return time();
        }

        try {
            return (new \DateTimeImmutable($value))->getTimestamp();
        } catch (\Exception) {
            return time();
        }
    }
}
