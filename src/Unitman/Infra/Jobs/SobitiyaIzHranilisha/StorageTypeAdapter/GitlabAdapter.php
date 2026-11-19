<?php

namespace App\Unitman\Infra\Jobs\SobitiyaIzHranilisha\StorageTypeAdapter;

use App\Unitman\Business\Model\Repo\RepoType;
use App\Unitman\Business\Model\SobitieIzHranilisha\DannieSobitiya;
use App\Unitman\Business\Model\SobitieIzHranilisha\SobitiePayload;
use App\Unitman\Business\Model\SobitieIzHranilisha\TipSobitiya;

final class GitlabAdapter implements StorageTypeAdapter
{

    const EMPTY_HASH = '0000000000000000000000000000000000000000';

    function poluchitDannieSobitiya(SobitiePayload $payload): DannieSobitiya
    {
        $data = $payload->data;

        if (!empty($data['before']) && $data['before'] === self::EMPTY_HASH) {
            $type = TipSobitiya::VETKA_SOZDANA;
        } else {
            $type = !empty($data['after']) && $data['after'] === self::EMPTY_HASH ? TipSobitiya::VETKA_UDALENA : TipSobitiya::KOD_OBNOVLEN;
        }

        $vetka = str_replace('refs/heads/', '', (string)($data['ref'] ?? ''));
        $vetka = str_replace('refs/', '', $vetka);

        if ($type === TipSobitiya::VETKA_UDALENA  || $type === TipSobitiya::VETKA_SOZDANA) {
            $unixtime = time();
        } else {
            $unixtime = $this->unixtimePoslednegoKommita($data);
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

        if (strpos((string)($data['ref'] ?? ''), 'refs/tags') !== false) {
            return false;
        }

        return !empty($data['event_name']) && $data['event_name'] === 'push';
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
}
