<?php

namespace App\Unitman\Infra\BackgroundJob\SobitiyaIzHranilisha\StorageTypeAdapter;

use App\Unitman\Business\Model\Repo\RepoType;
use App\Unitman\Business\Model\SobitieIzHranilisha;
use App\Unitman\Business\Model\SobitieIzHranilisha\DannieSobitiya;
use App\Unitman\Business\Model\SobitieIzHranilisha\TipSobitiya;
use App\Unitman\Infra\BackgroundJob\SobitiyaIzHranilisha\StorageTypeAdapter\StorageTypeAdapter;

final class GitlabAdapter implements StorageTypeAdapter
{

    function poluchitDannieSobitiya(SobitieIzHranilisha $sobitieIzHranilisha): DannieSobitiya
    {
        $data = json_decode($sobitieIzHranilisha->eventPayload, true);
        $type = !empty($data['after']) && $data['after'] === '0000000000000000000000000000000000000000'? TipSobitiya::VETKA_UDALENA : TipSobitiya::KOD_OBNOVLEN;
        $vetka = str_replace('refs/heads/', '', (string)$data['ref']);
        $vetka = str_replace('refs/', '', $vetka);

        if ($type === TipSobitiya::VETKA_UDALENA) {
            $unixtime = time();
        } else {
            if (empty($data['commits'])) {
                throw new \Exception('sobitie_iz_hranilisha.commits_not_found');
            }
            $lastCommit = end($data['commits']);
            $dateTime = new \DateTimeImmutable($lastCommit['timestamp']);
            $unixtime = $dateTime->getTimestamp();
        }

        return new DannieSobitiya($type, $vetka, $unixtime);
    }

    public function isSupport(RepoType $repoType): bool
    {
        return $repoType === RepoType::GITLAB;
    }

    public function esliValidnoeSobitie(SobitieIzHranilisha $sobitieIzHranilisha): bool
    {
        $data = json_decode($sobitieIzHranilisha->eventPayload, true);

        return !empty($data['event_name']) && $data['event_name'] === 'push';
    }
}
