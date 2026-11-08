<?php

declare(strict_types=1);

namespace App\Unitman\Business\UseCase\VolumeType;

use App\Unitman\Business\Command\VolumeType\PrikrepitFaylKTipuVoluma;
use App\Unitman\Business\Model\VolumeType\VolumeTypeFile;
use App\Unitman\Business\Port\MediatekaService;
use App\Unitman\Business\Port\VolumeType\VolumeTypeFileRepository;
use App\Unitman\Business\Port\VolumeType\VolumeTypeRepository;

/**
 * Привязка проектного архива медиатеки к типу тома.
 *
 * Привязать можно только проектный архив (не файл юнита), который ещё
 * не связан ни с одним типом этого проекта.
 */
final class PrikrepitFaylKTipuVolumaUseCase
{
    public function __construct(
        private readonly ProverkaDostupaKKatalogu $proverkaDostupa,
        private readonly VolumeTypeRepository $volumeTypeRepository,
        private readonly VolumeTypeFileRepository $volumeTypeFileRepository,
        private readonly MediatekaService $mediatekaService
    ) {
    }

    public function handle(PrikrepitFaylKTipuVoluma $command): void
    {
        $project = $this->proverkaDostupa->proveritPravaAdmina($command->projectId);
        $projectId = $project->getId();
        $type = $this->volumeTypeRepository->getByProjectAndCode($projectId, $command->code);
        $fileId = trim($command->fileId);

        if ($fileId === '') {
            throw new \DomainException('unit.volume_type.file_id_is_empty');
        }

        $owners = $this->mediatekaService->poluchitVladelcevFaylov([$fileId]);

        if (!array_key_exists($fileId, $owners)) {
            throw new \DomainException('unit.volume_type.file_not_found');
        }

        $owner = $owners[$fileId];

        if ($owner['unitId'] !== null) {
            throw new \DomainException('unit.volume_type.file_belongs_to_unit');
        }

        if ($owner['projectId'] !== $projectId) {
            throw new \DomainException('unit.volume_type.file_not_found');
        }

        $links = $this->volumeTypeFileRepository->najtiKodyTipovPoFaylam($projectId, [$fileId]);

        foreach ($links[$fileId] ?? [] as $linkedCode) {
            if ($linkedCode !== $type->code) {
                throw new \DomainException('unit.volume_type.file_already_linked');
            }
        }

        if (!$this->volumeTypeFileRepository->esliSvyazEst($type->id, $fileId)) {
            $this->volumeTypeFileRepository->save(
                new VolumeTypeFile($type->id, $projectId, $fileId, time())
            );
        }
    }
}
