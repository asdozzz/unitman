<?php

declare(strict_types=1);

namespace App\Unitman\Infra\Jobs\SobitiyaIzHranilisha\StorageTypeAdapter;

use App\Unitman\Business\Model\Repo\RepoType;
use App\Unitman\Business\Model\SobitieIzHranilisha\DannieSobitiya;
use App\Unitman\Business\Model\SobitieIzHranilisha\SobitiePayload;
use App\Unitman\Business\Model\SobitieIzHranilisha\TipSobitiya;

/**
 * GitFlic присылает события BRANCH_CREATE, BRANCH_UPDATE, BRANCH_DELETE,
 * MERGE и MERGE_REQUEST_*. Точная форма payload зависит от версии, поэтому
 * поля читаются терпимо к нескольким вариантам имён.
 */
final class GitflicAdapter implements StorageTypeAdapter
{
    public function isSupport(RepoType $repoType): bool
    {
        return $repoType === RepoType::GITFLIC;
    }

    public function esliValidnoeSobitie(SobitiePayload $payload): bool
    {
        return $this->tipSobitiya($payload->data) !== null;
    }

    public function poluchitDannieSobitiya(SobitiePayload $payload, string $mainBranch): DannieSobitiya
    {
        $data = $payload->data;
        $type = $this->tipSobitiya($data) ?? TipSobitiya::KOD_OBNOVLEN;
        $branch = $this->vetka($data);
        $unixtime = $this->unixtime($data) ?? time();

        if ($type === TipSobitiya::VETKA_SMERZHENA) {
            return new DannieSobitiya(
                $type,
                $this->istochnik($data) ?? $branch,
                $unixtime,
                $this->cel($data) ?? $mainBranch,
            );
        }

        return new DannieSobitiya($type, $branch, $unixtime);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function tipSobitiya(array $data): ?TipSobitiya
    {
        $event = strtoupper((string)(
            $data['event']
            ?? $data['eventType']
            ?? $data['event_name']
            ?? $data['type']
            ?? ''
        ));

        if ($event === '') {
            return null;
        }

        if (str_contains($event, 'BRANCH_CREATE')) {
            return TipSobitiya::VETKA_SOZDANA;
        }

        if (str_contains($event, 'BRANCH_DELETE')) {
            return TipSobitiya::VETKA_UDALENA;
        }

        if ($event === 'MERGE' || str_contains($event, 'MERGE_REQUEST_MERGE')) {
            return TipSobitiya::VETKA_SMERZHENA;
        }

        if (str_contains($event, 'BRANCH_UPDATE') || str_contains($event, 'PUSH')) {
            return TipSobitiya::KOD_OBNOVLEN;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function vetka(array $data): string
    {
        foreach ([
            $data['branch']['name'] ?? null,
            $data['branchName'] ?? null,
            $data['ref'] ?? null,
            $data['sourceBranch'] ?? null,
        ] as $candidate) {
            $name = $this->normalizovatVetku($candidate);
            if ($name !== '') {
                return $name;
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $data
     */
    private function istochnik(array $data): ?string
    {
        foreach ([
            $data['sourceBranch'] ?? null,
            $data['sourceBranchName'] ?? null,
            $data['source']['branch']['name'] ?? null,
            $data['mergeRequest']['sourceBranch'] ?? null,
            $data['mergeRequest']['sourceBranchName'] ?? null,
            $data['source']['name'] ?? null,
        ] as $candidate) {
            $name = $this->normalizovatVetku($candidate);
            if ($name !== '') {
                return $name;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function cel(array $data): ?string
    {
        foreach ([
            $data['targetBranch'] ?? null,
            $data['targetBranchName'] ?? null,
            $data['destination']['branch']['name'] ?? null,
            $data['mergeRequest']['targetBranch'] ?? null,
            $data['mergeRequest']['targetBranchName'] ?? null,
            $data['target']['name'] ?? null,
        ] as $candidate) {
            $name = $this->normalizovatVetku($candidate);
            if ($name !== '') {
                return $name;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function unixtime(array $data): ?int
    {
        foreach ([
            $data['timestamp'] ?? null,
            $data['createdAt'] ?? null,
            $data['date'] ?? null,
            $data['commit']['timestamp'] ?? null,
            $data['mergeRequest']['updatedAt'] ?? null,
        ] as $candidate) {
            if (!is_string($candidate) || $candidate === '') {
                continue;
            }

            $unixtime = strtotime($candidate);
            if ($unixtime !== false) {
                return $unixtime;
            }
        }

        return null;
    }

    private function normalizovatVetku(mixed $vetka): string
    {
        if (!is_string($vetka) || trim($vetka) === '') {
            return '';
        }

        $vetka = str_replace('refs/heads/', '', trim($vetka));

        return str_replace('refs/', '', $vetka);
    }
}
