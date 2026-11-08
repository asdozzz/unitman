<?php

declare(strict_types=1);

namespace App\Unitman\Business\UseCase\VolumeType;

use App\Unitman\Business\Command\VolumeType\UdalitTipVoluma;
use App\Unitman\Business\Port\VolumeType\VolumeTypeRepository;

/**
 * Удаление типа тома из каталога проекта.
 *
 * Тип с привязанными архивами удалить нельзя: сначала нужно отвязать файлы.
 */
final class UdalitTipVolumaUseCase
{
    public function __construct(
        private readonly ProverkaDostupaKKatalogu $proverkaDostupa,
        private readonly VolumeTypeRepository $volumeTypeRepository
    ) {
    }

    public function handle(UdalitTipVoluma $command): void
    {
        $project = $this->proverkaDostupa->proveritPravaAdmina($command->projectId);
        $type = $this->volumeTypeRepository->getByProjectAndCode($project->getId(), $command->code);

        if ($this->volumeTypeRepository->esliEstFayly($type->id)) {
            throw new \DomainException('unit.volume_type.has_files');
        }

        $this->volumeTypeRepository->udalit($type->id);
    }
}
