<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\SozdatUnit;
use App\Unitman\Business\Command\Unit\SozdatUnit\VolumeZapros;
use App\Unitman\Business\Model\Unit;
use App\Unitman\Business\Port\CanGeneateGuid;
use App\Unitman\Business\Port\MediatekaService;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\Unit\CanFindUnitDouble;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;
use App\Utils\Exception\TranslatorKeyword;

final class SozdatUnitUseCase
{
    public function __construct(
        private CanFindUnitDouble      $canFindUnitDouble,
        private ProjectRepository      $projectRepository,
        private UnitmanSecurityService $securityService,
        private CanGeneateGuid         $uuidGenerator,
        private UnitRepository         $unitRepository,
        private MediatekaService       $mediatekaService,
    )
    {
    }

    function handle(SozdatUnit $command): string
    {
        if ($this->canFindUnitDouble->isExistsDoubleByName($command->projectId, $command->unitName)) {
            throw new \DomainException(TranslatorKeyword::UNIT_NAME_ALREADY_EXISTS->value);
        }

        $project = $this->projectRepository->getById($command->projectId);

        if ($project->isDisable()) {
            throw new \DomainException('unit.project_is_disabled');
        }

        $userId = $this->securityService->getCurrentUserId();

        $fileIds = $this->extractFileIds($command->volumes);

        // Файлы загружаются до создания юнита как черновики медиатеки.
        // Проверяем их до сохранения юнита, чтобы не создавать юнит,
        // который затем не удастся связать с файлами.
        if ($fileIds !== [] && !$this->mediatekaService->vseFaylyDostupnyDlyaPrivyazki($fileIds)) {
            throw new \DomainException('unit.files.not_found');
        }

        $unitId = $this->uuidGenerator->makeGuid();
        $prozesId = $this->uuidGenerator->makeGuid();
        $unit = Unit::sozdatUnit($unitId, $userId, $prozesId, $command);
        $this->unitRepository->save($unit);

        if ($fileIds !== []) {
            $this->mediatekaService->prikrepitFaylyKUnitu($fileIds, $unitId);
        }

        return $unit->getId();
    }

    /**
     * Клиент передаёт тома парами `{dir, name, fileId}`, где `fileId` может
     * отсутствовать — тогда том описывает только каталог без файла.
     *
     * @param VolumeZapros[] $volumes
     * @return string[]
     */
    private function extractFileIds(array $volumes): array
    {
        $result = [];

        foreach ($volumes as $volume) {
            $fileId = $volume->fileId;

            if ($fileId === null) {
                continue;
            }

            $fileId = trim($fileId);
            if ($fileId === '') {
                continue;
            }

            $result[$fileId] = $fileId;
        }

        return array_values($result);
    }
}
