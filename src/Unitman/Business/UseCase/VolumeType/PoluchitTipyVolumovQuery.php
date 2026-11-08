<?php

declare(strict_types=1);

namespace App\Unitman\Business\UseCase\VolumeType;

use App\Unitman\Business\Command\VolumeType\PoluchitTipyVolumov;
use App\Unitman\Business\Port\MediatekaService;
use App\Unitman\Business\Port\VolumeType\VolumeTypeFileRepository;
use App\Unitman\Business\Port\VolumeType\VolumeTypeRepository;
use App\Unitman\Business\ReadModel\VolumeType\VolumeTypeFileItem;
use App\Unitman\Business\ReadModel\VolumeType\VolumeTypeList;

/**
 * Каталог типов томов проекта вместе с привязанными архивами.
 */
final class PoluchitTipyVolumovQuery
{
    public function __construct(
        private readonly ProverkaDostupaKKatalogu $proverkaDostupa,
        private readonly VolumeTypeRepository $volumeTypeRepository,
        private readonly VolumeTypeFileRepository $volumeTypeFileRepository,
        private readonly MediatekaService $mediatekaService
    ) {
    }

    /**
     * @return VolumeTypeList[]
     */
    public function handle(PoluchitTipyVolumov $command): array
    {
        $project = $this->proverkaDostupa->proveritChlenstvoProekta($command->projectId);
        $projectId = $project->getId();

        $result = [];

        foreach ($this->volumeTypeRepository->najtiPoProektu($projectId) as $type) {
            $files = [];

            foreach ($this->volumeTypeFileRepository->najtiPoTipu($type->id) as $link) {
                $meta = $this->mediatekaService->poluchitFaylProekta($link->fileId, $projectId);
                if ($meta === null) {
                    continue;
                }

                $files[] = new VolumeTypeFileItem(
                    $link->fileId,
                    $meta['name'],
                    $meta['size'],
                    $meta['createdAt']
                );
            }

            $result[] = new VolumeTypeList($type->code, $type->name, $files);
        }

        return $result;
    }
}
