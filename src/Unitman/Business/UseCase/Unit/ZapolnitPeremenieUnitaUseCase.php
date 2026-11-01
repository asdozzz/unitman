<?php

declare(strict_types=1);

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\ZapolnitPeremenieUnita;
use App\Unitman\Business\Model\Unit;
use App\Unitman\Business\Model\Unit\VariableValuesNormalizer;
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
        $this->proveritFaylyVolumes($unit, $command->values);
        $unit->zapolnitPeremenie($command->values);
        if ($unit->esliZapushen() || $unit->esliPodgotovlen()) {
            $prozesId = $this->canGeneateGuid->makeGuid();
            $unit->dobavitProzesObnovleniya($userId, $prozesId, true, $project->poluchitNastroikiHuka()->obnovlenieBezSbrosaPodgotovki);
        }
        $this->unitRepository->save($unit);
    }

    /**
     * Файлы, указанные в значениях переменных томов `unitman_volumes`,
     * должны существовать и быть прикреплены именно к этому юниту.
     *
     * @param array<array-key, mixed> $values
     */
    private function proveritFaylyVolumes(Unit $unit, array $values): void
    {
        $config = $unit->getConfig();

        if ($config === null || !$config->esliEstVolumes()) {
            return;
        }

        $normalizedValues = VariableValuesNormalizer::toMap($values);
        $fileIds = [];

        foreach ($config->getVolumes() as $volume) {
            $variableId = $volume->getVariable();

            if ($variableId === null) {
                continue;
            }

            $value = $normalizedValues[$variableId] ?? null;

            if ($value === null || $value === '') {
                // Отсутствие значения валидирует сам агрегат.
                continue;
            }

            $fileIds[$value] = $value;
        }

        if (empty($fileIds)) {
            return;
        }

        if (!$this->mediatekaService->vseFaylyPrinadlezhatUnitu(array_values($fileIds), $unit->getId())) {
            throw new \DomainException('unit.volumes.file_not_found');
        }
    }
}
