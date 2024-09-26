<?php

namespace App\Unitman\Infra\Jobs\SobitiyaIzHranilisha\StorageTypeAdapter;

use App\Unitman\Business\Model\Repo\RepoType;
use App\Unitman\Business\Model\SobitieIzHranilisha;
use App\Unitman\Business\Model\SobitieIzHranilisha\DannieSobitiya;
use App\Unitman\Business\Model\SobitieIzHranilisha\TipSobitiya;

final class GithubAdapter implements StorageTypeAdapter
{
    function poluchitDannieSobitiya(SobitieIzHranilisha $sobitieIzHranilisha): DannieSobitiya
    {
        $data = json_decode($sobitieIzHranilisha->eventPayload, true);
        if (!empty($data['created'])) {
            $type = TipSobitiya::VETKA_SOZDANA;
        } else {
            $type = !empty($data['deleted'])? TipSobitiya::VETKA_UDALENA : TipSobitiya::KOD_OBNOVLEN;
        }

        $vetka = str_replace('refs/heads/', '', (string)$data['ref']);
        $vetka = str_replace('refs/', '', $vetka);
        if ($type === TipSobitiya::VETKA_UDALENA || $type === TipSobitiya::VETKA_SOZDANA) {
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
        return $repoType === RepoType::GITHUB;
    }

    public function esliValidnoeSobitie(SobitieIzHranilisha $sobitieIzHranilisha): bool
    {
        $data = json_decode($sobitieIzHranilisha->eventPayload, true);

        if (strpos((string)$data['ref'], 'refs/tags') !== false) {
            return false;
        }

        return !empty($data['ref']) && (!empty($data['commits']) || !empty($data['deleted']) || !empty($data['created']));
    }
}
