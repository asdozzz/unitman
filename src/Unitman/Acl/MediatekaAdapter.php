<?php

declare(strict_types=1);

namespace App\Unitman\Acl;

use App\Mediateka\Api\MediatekaApi;
use App\Unitman\Business\Port\MediatekaService;

final class MediatekaAdapter implements MediatekaService
{
    public function __construct(private readonly MediatekaApi $mediatekaApi)
    {
    }

    /**
     * @param string[] $fileIds
     */
    public function vseFaylyPrinadlezhatUnitu(array $fileIds, string $unitId): bool
    {
        foreach ($fileIds as $fileId) {
            if (!$this->mediatekaApi->prinadlezhitLiFaylUnitu($fileId, $unitId)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param string[] $fileIds
     */
    public function vseFaylyDostupnyDlyaPrivyazki(array $fileIds): bool
    {
        return $this->mediatekaApi->vseFaylyDostupnyDlyaPrivyazki($fileIds);
    }

    /**
     * @param string[] $fileIds
     */
    public function prikrepitFaylyKUnitu(array $fileIds, string $unitId): void
    {
        $this->mediatekaApi->prikrepitFaylyKUnitu($unitId, $fileIds);
    }

    /**
     * @return array{name: string, url: string}|null
     */
    public function poluchitFaylDlyaVoluma(string $fileId, string $unitId, ?int $ttl = null): ?array
    {
        $file = $this->mediatekaApi->poluchitFaylUnitaPoImUnit($fileId, $unitId);

        if ($file === null) {
            return null;
        }

        return [
            'name' => $file->getName(),
            'url' => $this->mediatekaApi->poluchitSsylkuDlyaFayla($fileId, $ttl),
        ];
    }

    public function udaliFayliUnita(string $unitId): void
    {
        $this->mediatekaApi->udaliFayliUnita($unitId);
    }
}
