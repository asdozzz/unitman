<?php

declare(strict_types=1);

namespace App\Unitman\Business\UseCase\VolumeType;

use App\Unitman\Business\Command\VolumeType\OtkrepitFaylOtTipaVoluma;
use App\Unitman\Business\Port\MediatekaService;
use App\Unitman\Business\Port\VolumeType\VolumeTypeFileRepository;
use App\Unitman\Business\Port\VolumeType\VolumeTypeRepository;

/**
 * Отвязка архива от типа тома.
 *
 * `udalitFayl = true` дополнительно удаляет архив из медиатеки проекта.
 */
final class OtkrepitFaylOtTipaVolumaUseCase
{
    public function __construct(
        private readonly ProverkaDostupaKKatalogu $proverkaDostupa,
        private readonly VolumeTypeRepository $volumeTypeRepository,
        private readonly VolumeTypeFileRepository $volumeTypeFileRepository,
        private readonly MediatekaService $mediatekaService
    ) {
    }

    public function handle(OtkrepitFaylOtTipaVoluma $command): void
    {
        $project = $this->proverkaDostupa->proveritPravaAdmina($command->projectId);
        $projectId = $project->getId();
        $type = $this->volumeTypeRepository->getByProjectAndCode($projectId, $command->code);
        $fileId = trim($command->fileId);

        if ($fileId === '') {
            throw new \DomainException('unit.volume_type.file_id_is_empty');
        }

        if (!$this->volumeTypeFileRepository->esliSvyazEst($type->id, $fileId)) {
            throw new \DomainException('unit.volume_type.file_not_linked');
        }

        $this->volumeTypeFileRepository->udalitSvyaz($type->id, $fileId);

        if ($command->udalitFayl) {
            $this->mediatekaService->udaliFaylProekta($fileId, $projectId);
        }
    }
}
