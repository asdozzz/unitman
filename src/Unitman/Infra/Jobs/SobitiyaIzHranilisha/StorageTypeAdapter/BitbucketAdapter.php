<?php

declare(strict_types=1);

namespace App\Unitman\Infra\Jobs\SobitiyaIzHranilisha\StorageTypeAdapter;

use App\Unitman\Business\Model\Repo\RepoType;
use App\Unitman\Business\Model\SobitieIzHranilisha\DannieSobitiya;
use App\Unitman\Business\Model\SobitieIzHranilisha\SobitiePayload;
use App\Unitman\Business\Model\SobitieIzHranilisha\TipSobitiya;

/**
 * Bitbucket Cloud присылает push через push.changes[], а слияние — через
 * pullrequest.state = MERGED. Дополнительно распознаётся merge-коммит
 * "Merged in feature/x (pull request #12)".
 */
final class BitbucketAdapter implements StorageTypeAdapter
{
    public function isSupport(RepoType $repoType): bool
    {
        return $repoType === RepoType::BITBUCKET;
    }

    public function esliValidnoeSobitie(SobitiePayload $payload): bool
    {
        $data = $payload->data;

        if (isset($data['pullrequest']) && is_array($data['pullrequest'])) {
            return ($data['pullrequest']['state'] ?? null) === 'MERGED'
                || !empty($data['pullrequest']['merged_on']);
        }

        $changes = $data['push']['changes'] ?? null;
        if (!is_array($changes)) {
            return false;
        }

        foreach ($changes as $change) {
            if (!is_array($change)) {
                continue;
            }

            if (($change['new']['type'] ?? '') === 'tag') {
                continue;
            }

            return !empty($change['new']['name']);
        }

        return false;
    }

    public function poluchitDannieSobitiya(SobitiePayload $payload, string $mainBranch): DannieSobitiya
    {
        $data = $payload->data;

        if (isset($data['pullrequest']) && is_array($data['pullrequest'])) {
            return $this->danniePullRequesta($data['pullrequest']);
        }

        $changes = $data['push']['changes'] ?? [];
        $change = is_array($changes) ? end($changes) : null;
        $change = is_array($change) ? $change : [];

        /** @var array<string, mixed> $new */
        $new = is_array($change['new'] ?? null) ? $change['new'] : [];
        $vetka = $this->normalizovatVetku((string)($new['name'] ?? ''));

        if (!empty($change['created'])) {
            $type = TipSobitiya::VETKA_SOZDANA;
        } elseif (!empty($change['closed'])) {
            $type = TipSobitiya::VETKA_UDALENA;
        } else {
            $type = TipSobitiya::KOD_OBNOVLEN;
        }

        $unixtime = $this->unixtimePoslednegoKommita($change);

        if ($unixtime === null) {
            $unixtime = $this->unixtimeIzStroki($new['target']['date'] ?? null);
        }

        if ($type === TipSobitiya::VETKA_UDALENA || $type === TipSobitiya::VETKA_SOZDANA) {
            return new DannieSobitiya($type, $vetka, $unixtime);
        }

        if ($vetka === $mainBranch) {
            $source = $this->vetkaIstochnikaIzKommitov($change, $mainBranch);

            if ($source !== null) {
                return new DannieSobitiya(TipSobitiya::VETKA_SMERZHENA, $source, $unixtime, $vetka);
            }
        }

        return new DannieSobitiya($type, $vetka, $unixtime);
    }

    /**
     * @param array<string, mixed> $pullRequest
     */
    private function danniePullRequesta(array $pullRequest): DannieSobitiya
    {
        return new DannieSobitiya(
            TipSobitiya::VETKA_SMERZHENA,
            $this->normalizovatVetku((string)($pullRequest['source']['branch']['name'] ?? '')),
            $this->unixtimeIzStroki($pullRequest['merged_on'] ?? $pullRequest['updated_on'] ?? null),
            $this->normalizovatVetku((string)($pullRequest['destination']['branch']['name'] ?? '')),
        );
    }

    /**
     * @param array<string, mixed> $change
     */
    private function vetkaIstochnikaIzKommitov(array $change, string $mainBranch): ?string
    {
        $commits = $change['commits'] ?? null;

        if (!is_array($commits)) {
            return null;
        }

        foreach (array_reverse($commits) as $commit) {
            if (!is_array($commit) || !isset($commit['message'])) {
                continue;
            }

            $message = (string)$commit['message'];

            if (preg_match('/^Merged in ([^\s(]+) \(pull request #\d+\)/m', $message, $matches) === 1) {
                $source = trim($matches[1]);

                if ($source !== '' && $source !== $mainBranch) {
                    return $source;
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $change
     */
    private function unixtimePoslednegoKommita(array $change): ?int
    {
        $commits = $change['commits'] ?? null;

        if (!is_array($commits) || $commits === []) {
            return null;
        }

        $lastCommit = end($commits);

        return is_array($lastCommit) ? $this->unixtimeIzStroki($lastCommit['date'] ?? null) : null;
    }

    private function normalizovatVetku(string $vetka): string
    {
        $vetka = str_replace('refs/heads/', '', $vetka);

        return str_replace('refs/', '', $vetka);
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
