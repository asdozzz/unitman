<?php

declare(strict_types=1);

namespace App\Unitman\Business\UseCase\VolumeType;

use App\Unitman\Business\Command\VolumeType\DobavitTipVoluma;
use App\Unitman\Business\Model\VolumeType\VolumeType;
use App\Unitman\Business\Port\CanGeneateGuid;
use App\Unitman\Business\Port\VolumeType\VolumeTypeRepository;

/**
 * Добавление типа тома в каталог проекта.
 *
 * Уникальность пары `project_id + code` обеспечивает репозиторий
 * (`unit.volume_type.code_is_not_unique`).
 */
final class DobavitTipVolumaUseCase
{
    public function __construct(
        private readonly ProverkaDostupaKKatalogu $proverkaDostupa,
        private readonly VolumeTypeRepository $volumeTypeRepository,
        private readonly CanGeneateGuid $canGeneateGuid
    ) {
    }

    public function handle(DobavitTipVoluma $command): void
    {
        $project = $this->proverkaDostupa->proveritPravaAdmina($command->projectId);

        $type = new VolumeType(
            $this->canGeneateGuid->makeGuid(),
            $project->getId(),
            $command->code,
            $command->name,
            time()
        );

        $this->volumeTypeRepository->save($type);
    }
}
