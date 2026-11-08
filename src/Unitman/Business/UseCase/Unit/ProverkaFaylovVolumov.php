<?php

declare(strict_types=1);

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\SozdatUnit\VolumeZapros as VolumeZaprosSozdaniya;
use App\Unitman\Business\Command\Unit\ZapolnitPeremenieUnita\VolumeZapros as VolumeZaprosZapolneniya;
use App\Unitman\Business\Port\MediatekaService;
use App\Unitman\Business\Port\VolumeType\VolumeTypeFileRepository;

/**
 * Общая проверка `fileId` в блоке `unitman_volumes` для `sozdat`
 * и `zapolnitPeremenie`.
 *
 * Разрешены три варианта:
 * 1. черновик медиатеки (`unitId` и `projectId` пусты) — файл прикрепляется к юниту;
 * 2. архив каталога проекта, связанный с типом, у которого `code` совпадает с `dir` тома;
 * 3. файл уже прикреплён к этому же юниту (повторное заполнение переменных).
 *
 * Остальные варианты отклоняются `DomainException`.
 */
final class ProverkaFaylovVolumov
{
    public function __construct(
        private readonly MediatekaService $mediatekaService,
        private readonly VolumeTypeFileRepository $volumeTypeFileRepository,
    )
    {
    }

    /**
     * @param array<array-key, VolumeZaprosSozdaniya|VolumeZaprosZapolneniya> $volumes
     *
     * @return string[] идентификаторы черновиков, которые нужно прикрепить к юниту
     */
    public function proverit(string $projectId, array $volumes, ?string $unitId = null): array
    {
        $zaprosy = [];

        foreach ($volumes as $volume) {
            $fileId = $volume->fileId;

            if ($fileId === null) {
                continue;
            }

            $fileId = trim($fileId);
            if ($fileId === '') {
                continue;
            }

            $dir = trim($volume->dir);

            // Один и тот же файл может быть указан для разных томов — проверяем каждую пару.
            $zaprosy[$dir . '|' . $fileId] = ['dir' => $dir, 'fileId' => $fileId];
        }

        if ($zaprosy === []) {
            return [];
        }

        $fileIds = array_values(array_unique(array_column($zaprosy, 'fileId')));

        $vladelcy = $this->mediatekaService->poluchitVladelcevFaylov($fileIds);
        $kodyTipov = $this->volumeTypeFileRepository->najtiKodyTipovPoFaylam($projectId, $fileIds);

        $chernoviki = [];

        foreach ($zaprosy as $zapros) {
            $fileId = $zapros['fileId'];
            $dir = $zapros['dir'];

            $vladelec = $vladelcy[$fileId] ?? null;

            if ($vladelec === null) {
                throw new \DomainException('unit.files.not_found');
            }

            if ($vladelec['unitId'] === null && $vladelec['projectId'] === null) {
                $chernoviki[$fileId] = $fileId;
                continue;
            }

            if ($unitId !== null && $vladelec['unitId'] === $unitId) {
                continue;
            }

            if ($vladelec['projectId'] !== $projectId) {
                throw new \DomainException('unit.files.not_found');
            }

            $kody = $kodyTipov[$fileId] ?? [];

            if ($kody === []) {
                throw new \DomainException('unit.volumes.file_not_in_catalog');
            }

            if (!in_array($dir, $kody, true)) {
                throw new \DomainException('unit.volumes.file_type_mismatch');
            }
        }

        return array_values($chernoviki);
    }
}
