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

    /**
     * @param string[] $fileIds
     *
     * @return array<string, array{unitId: ?string, projectId: ?string}>
     */
    public function poluchitVladelcevFaylov(array $fileIds): array
    {
        return $this->mediatekaApi->poluchitVladelcevFaylov($fileIds);
    }

    /**
     * @return array{name: string, url: string}|null
     */
    public function poluchitFaylProektaDlyaVoluma(string $fileId, string $projectId, ?int $ttl = null): ?array
    {
        $file = $this->mediatekaApi->poluchitFaylProektaPoImProekta($fileId, $projectId);

        if ($file === null) {
            return null;
        }

        return [
            'name' => $file->getName(),
            'url' => $this->mediatekaApi->poluchitSsylkuDlyaFayla($file->getId(), $ttl),
        ];
    }

    /**
     * @return array{name: string, size: int, createdAt: int}|null
     */
    public function poluchitFaylProekta(string $fileId, string $projectId): ?array
    {
        $file = $this->mediatekaApi->poluchitFaylProektaPoImProekta($fileId, $projectId);

        if ($file === null) {
            return null;
        }

        return [
            'name' => $file->getName(),
            'size' => $file->getSize(),
            'createdAt' => $file->getCreatedAt(),
        ];
    }

    public function udaliFaylProekta(string $fileId, string $projectId): void
    {
        if ($this->mediatekaApi->poluchitFaylProektaPoImProekta($fileId, $projectId) === null) {
            throw new \DomainException('unit.volume_type.file_not_found');
        }

        $this->mediatekaApi->udaliFayl($fileId);
    }
}
