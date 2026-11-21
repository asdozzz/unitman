<?php

declare(strict_types=1);

namespace App\Unitman\Business\UseCase\Unit\Redaktor;

use App\Unitman\Business\Command\Unit\Redaktor\SpisokRedaktorovUnita;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;
use App\Unitman\Business\ReadModel\Unit\RedaktorUnitaReadModel;
use App\Unitman\Business\ReadModel\Unit\SpisokRedaktorovReadModel;

/**
 * Список прикреплённых редакторов юнита с состоянием их личных токенов.
 */
final class SpisokRedaktorovUnitaQuery
{
    public function __construct(
        private UnitRepository $unitRepository,
        private ProjectRepository $projectRepository,
        private PravilaRedaktora $pravilaRedaktora,
        private OpredelitelRezhimaRedaktora $opredelitelRezhima,
        private UnitmanSecurityService $securityService,
    )
    {
    }

    public function handle(SpisokRedaktorovUnita $command): SpisokRedaktorovReadModel
    {
        $unit = $this->unitRepository->getById($command->unitId);
        $project = $this->projectRepository->getById($unit->getProjectId());

        $this->pravilaRedaktora->trebovatPravoUpravleniya($unit, $project);

        $redaktory = [];

        foreach ($unit->getRedaktory() as $userId) {
            $tokeny = $this->opredelitelRezhima->zagruzitTokeny($userId);

            $redaktory[] = new RedaktorUnitaReadModel(
                $userId,
                $this->securityService->getEmailOrNicknameByUserId($userId),
                $this->opredelitelRezhima->pokryvaetProekt($tokeny, $project),
            );
        }

        return new SpisokRedaktorovReadModel(
            $unit->getId(),
            $unit->getRedaktirovanieVklucheno(),
            $redaktory,
        );
    }
}
