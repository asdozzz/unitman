<?php

declare(strict_types=1);

namespace App\Unitman\Tests\Support;

use App\Unitman\Business\Port\MediatekaService;

/**
 * Медиатека в памяти для тестов каталога типов томов и валидации `fileId`.
 *
 * Владельцы файлов задаются вручную в формате порта:
 * `fileId => ['unitId' => ..., 'projectId' => ...]`.
 */
final class FakeMediatekaService implements MediatekaService
{
    /**
     * @var array<string, array{unitId: ?string, projectId: ?string}>
     */
    public array $vladelcy = [];

    /**
     * @var array<string, array{name: string, size: int, createdAt: int}>
     */
    public array $faylyProekta = [];

    /**
     * @var string[]
     */
    public array $prikreplennye = [];

    /**
     * @var string[]
     */
    public array $udalennieFaylyProekta = [];

    /**
     * @param string[] $fileIds
     */
    public function vseFaylyPrinadlezhatUnitu(array $fileIds, string $unitId): bool
    {
        foreach ($fileIds as $fileId) {
            if (($this->vladelcy[$fileId]['unitId'] ?? null) !== $unitId) {
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
        foreach ($fileIds as $fileId) {
            $owner = $this->vladelcy[$fileId] ?? null;
            if ($owner === null || $owner['unitId'] !== null || $owner['projectId'] !== null) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param string[] $fileIds
     */
    public function prikrepitFaylyKUnitu(array $fileIds, string $unitId): void
    {
        foreach ($fileIds as $fileId) {
            $this->prikreplennye[$fileId] = $unitId;
            $this->vladelcy[$fileId] = ['unitId' => $unitId, 'projectId' => null];
        }
    }

    /**
     * @return array{name: string, url: string}|null
     */
    public function poluchitFaylDlyaVoluma(string $fileId, string $unitId, ?int $ttl = null): ?array
    {
        $owner = $this->vladelcy[$fileId] ?? null;
        if ($owner === null || $owner['unitId'] !== $unitId) {
            return null;
        }

        return ['name' => $fileId . '.zip', 'url' => 'https://storage.test/units/' . $unitId . '/' . $fileId . '.zip'];
    }

    public function udaliFayliUnita(string $unitId): void
    {
    }

    /**
     * @param string[] $fileIds
     *
     * @return array<string, array{unitId: ?string, projectId: ?string}>
     */
    public function poluchitVladelcevFaylov(array $fileIds): array
    {
        $result = [];

        foreach ($fileIds as $fileId) {
            if (array_key_exists($fileId, $this->vladelcy)) {
                $result[$fileId] = $this->vladelcy[$fileId];
            }
        }

        return $result;
    }

    /**
     * @return array{name: string, url: string}|null
     */
    public function poluchitFaylProektaDlyaVoluma(string $fileId, string $projectId, ?int $ttl = null): ?array
    {
        $owner = $this->vladelcy[$fileId] ?? null;
        if ($owner === null || $owner['projectId'] !== $projectId) {
            return null;
        }

        return [
            'name' => $fileId . '.zip',
            'url' => 'https://storage.test/projects/' . $projectId . '/' . $fileId . '.zip',
        ];
    }

    /**
     * @return array{name: string, size: int, createdAt: int}|null
     */
    public function poluchitFaylProekta(string $fileId, string $projectId): ?array
    {
        $owner = $this->vladelcy[$fileId] ?? null;
        if ($owner === null || $owner['projectId'] !== $projectId) {
            return null;
        }

        return $this->faylyProekta[$fileId] ?? ['name' => $fileId . '.zip', 'size' => 100, 'createdAt' => 1000];
    }

    public function udaliFaylProekta(string $fileId, string $projectId): void
    {
        $this->udalennieFaylyProekta[] = $fileId;
        unset($this->vladelcy[$fileId], $this->faylyProekta[$fileId]);
    }
}
