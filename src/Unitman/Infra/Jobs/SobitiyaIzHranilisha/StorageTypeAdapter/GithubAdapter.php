<?php

namespace App\Unitman\Infra\Jobs\SobitiyaIzHranilisha\StorageTypeAdapter;

use App\Unitman\Business\Model\Repo\RepoType;
use App\Unitman\Business\Model\SobitieIzHranilisha\DannieSobitiya;
use App\Unitman\Business\Model\SobitieIzHranilisha\SobitiePayload;
use App\Unitman\Business\Model\SobitieIzHranilisha\TipSobitiya;

final class GithubAdapter implements StorageTypeAdapter
{
    function poluchitDannieSobitiya(SobitiePayload $payload): DannieSobitiya
    {
        $data = $payload->data;

        if (!empty($data['created'])) {
            $type = TipSobitiya::VETKA_SOZDANA;
        } else {
            $type = !empty($data['deleted'])? TipSobitiya::VETKA_UDALENA : TipSobitiya::KOD_OBNOVLEN;
        }

        $vetka = str_replace('refs/heads/', '', (string)($data['ref'] ?? ''));
        $vetka = str_replace('refs/', '', $vetka);
        if ($type === TipSobitiya::VETKA_UDALENA || $type === TipSobitiya::VETKA_SOZDANA) {
            $unixtime = time();
        } else {
            $unixtime = $this->unixtimePoslednegoKommita($data);
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

        if (strpos((string)($data['ref'] ?? ''), 'refs/tags') !== false) {
            return false;
        }

        return !empty($data['ref']) && (!empty($data['commits']) || !empty($data['deleted']) || !empty($data['created']));
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
