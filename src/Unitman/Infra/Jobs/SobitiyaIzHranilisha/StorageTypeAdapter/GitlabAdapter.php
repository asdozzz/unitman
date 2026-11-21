<?php

namespace App\Unitman\Infra\Jobs\SobitiyaIzHranilisha\StorageTypeAdapter;

use App\Unitman\Business\Model\Repo\RepoType;
use App\Unitman\Business\Model\SobitieIzHranilisha\DannieSobitiya;
use App\Unitman\Business\Model\SobitieIzHranilisha\SobitiePayload;
use App\Unitman\Business\Model\SobitieIzHranilisha\TipSobitiya;

final class GitlabAdapter implements StorageTypeAdapter
{

    const EMPTY_HASH = '0000000000000000000000000000000000000000';

    function poluchitDannieSobitiya(SobitiePayload $payload, string $mainBranch): DannieSobitiya
    {
        $data = $payload->data;

        if ($this->esliMergeRequest($data)) {
            return $this->dannieMergeRequesta($data);
        }

        if (!empty($data['before']) && $data['before'] === self::EMPTY_HASH) {
            $type = TipSobitiya::VETKA_SOZDANA;
        } else {
            $type = !empty($data['after']) && $data['after'] === self::EMPTY_HASH ? TipSobitiya::VETKA_UDALENA : TipSobitiya::KOD_OBNOVLEN;
        }

        $vetka = $this->normalizovatVetku((string)($data['ref'] ?? ''));

        if ($type === TipSobitiya::VETKA_UDALENA || $type === TipSobitiya::VETKA_SOZDANA) {
            return new DannieSobitiya($type, $vetka, time());
        }

        $unixtime = $this->unixtimePoslednegoKommita($data);

        /*
         * Push в основную ветку часто является следствием мерджа. Если в
         * коммитах удалось найти merge-коммит именно в mainBranch, сообщаем
         * о слиянии ветки юнита, а не об обновлении кода основной ветки.
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
        return $repoType === RepoType::GITLAB;
    }

    public function esliValidnoeSobitie(SobitiePayload $payload): bool
    {
        $data = $payload->data;

        if ($this->esliMergeRequest($data)) {
            return $this->esliZavershenniMergeRequest($data);
        }

        if (strpos((string)($data['ref'] ?? ''), 'refs/tags') !== false) {
            return false;
        }

        return !empty($data['event_name']) && $data['event_name'] === 'push';
    }

    /**
     * @param array<string, mixed> $data
     */
    private function esliMergeRequest(array $data): bool
    {
        return ($data['object_kind'] ?? null) === 'merge_request';
    }

    /**
     * GitLab присылает и промежуточные состояния MR: открытие, approve и т.п.
     * Нас интересует только факт слияния.
     *
     * @param array<string, mixed> $data
     */
    private function esliZavershenniMergeRequest(array $data): bool
    {
        $attributes = $data['object_attributes'] ?? null;

        if (!is_array($attributes)) {
            return false;
        }

        return ($attributes['action'] ?? null) === 'merge'
            || ($attributes['state'] ?? null) === 'merged';
    }

    /**
     * @param array<string, mixed> $data
     */
    private function dannieMergeRequesta(array $data): DannieSobitiya
    {
        $attributes = $data['object_attributes'] ?? null;

        if (!is_array($attributes)) {
            $attributes = [];
        }

        $istochnik = $this->normalizovatVetku(
            (string)($attributes['source_branch'] ?? $data['source_branch'] ?? '')
        );
        $cel = $this->normalizovatVetku(
            (string)($attributes['target_branch'] ?? $data['target_branch'] ?? '')
        );

        return new DannieSobitiya(
            TipSobitiya::VETKA_SMERZHENA,
            $istochnik,
            $this->unixtimeIzStroki($attributes['merged_at'] ?? $attributes['updated_at'] ?? null),
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

            /*
             * Форматы GitLab: "Merge branch 'feature/x' into 'main'" и
             * "Merge branches 'a' and 'b' into 'main'". Цель обязана совпадать
             * с основной ветвью, иначе это вложенный мердж внутри ветки юнита.
             */
            if (preg_match('/^Merge branch(?:es)? (.+) into [\'"]?([^\'"\r\n]+)[\'"]?$/m', $message, $matches) !== 1) {
                continue;
            }

            if (trim($matches[2]) !== $mainBranch) {
                continue;
            }

            preg_match_all("/'([^']+)'/", $matches[1], $sources);

            foreach ($sources[1] as $source) {
                $source = trim($source);

                if ($source !== '' && $source !== $mainBranch) {
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
