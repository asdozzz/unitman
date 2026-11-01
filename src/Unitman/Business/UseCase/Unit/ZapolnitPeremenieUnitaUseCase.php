<?php

declare(strict_types=1);

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\ZapolnitPeremenieUnita;
use App\Unitman\Business\Command\Unit\ZapolnitPeremenieUnita\VolumeZapros;
use App\Unitman\Business\Port\CanGeneateGuid;
use App\Unitman\Business\Port\MediatekaService;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;

final class ZapolnitPeremenieUnitaUseCase
{
    public function __construct(
        private UnitRepository $unitRepository,
        private ProjectRepository $projectRepository,
        private UnitmanSecurityService $securityService,
        private CanGeneateGuid $canGeneateGuid,
        private MediatekaService $mediatekaService
    )
    {
    }

    function handle(ZapolnitPeremenieUnita $command): void
    {
        $unit = $this->unitRepository->getById($command->id);
        $project = $this->projectRepository->getById($unit->getProjectId());
        $userId = $this->securityService->getCurrentUserId();
        $projectUser = $project->getProjectUserById($userId);
        $unit->proverkaPrav($projectUser);
        $this->proveritFaylyVolumes($command->volumes, $unit->getId());
        $unit->zapolnitPeremenie($command->values, $command->volumes);
        if ($unit->esliZapushen() || $unit->esliPodgotovlen()) {
            $prozesId = $this->canGeneateGuid->makeGuid();
            $unit->dobavitProzesObnovleniya($userId, $prozesId, true, $project->poluchitNastroikiHuka()->obnovlenieBezSbrosaPodgotovki);
        }
        $this->unitRepository->save($unit);
    }

    /**
     * Файлы, переданные для томов `unitman_volumes`, должны существовать
     * и быть прикреплены именно к этому юниту. Наличие `dir` в конфиге
     * валидирует сам агрегат.
     *
     * @param VolumeZapros[] $volumes
     */
    private function proveritFaylyVolumes(array $volumes, string $unitId): void
    {
        $fileIds = [];

        foreach ($volumes as $volume) {
            $fileId = $volume->fileId;

            if ($fileId === null || trim($fileId) === '') {
                continue;
            }

            $fileIds[$fileId] = $fileId;
        }

        if (empty($fileIds)) {
            return;
        }

        if (!$this->mediatekaService->vseFaylyPrinadlezhatUnitu(array_values($fileIds), $unitId)) {
            throw new \DomainException('unit.volumes.file_not_found');
        }
    }
}
