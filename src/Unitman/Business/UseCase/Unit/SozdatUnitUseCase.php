<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\SozdatUnit;
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

        $fileIds = $this->normalizeFileIds($command->fileIds);

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
     * @param string[] $fileIds
     * @return string[]
     */
    private function normalizeFileIds(array $fileIds): array
    {
        $result = [];

        foreach ($fileIds as $fileId) {
            if (!is_string($fileId)) {
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
