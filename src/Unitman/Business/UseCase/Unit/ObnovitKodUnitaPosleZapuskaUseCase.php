<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\ObnovitKodUnitaPosleZapuska;
use App\Unitman\Business\Model\Runner\JobId;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\Port\Unit\UmeetObnovlyatKodUnitaPosleZapuska;
use App\Unitman\Business\Port\Unit\UmeetObnovlyatKodUnitaPosleZapuskaBezSbrosaPodgotovki;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;

final class ObnovitKodUnitaPosleZapuskaUseCase
{
    public function __construct(
        private UnitRepository $unitRepository,
        private ProjectRepository $projectRepository,
        private UmeetObnovlyatKodUnitaPosleZapuska $umeetObnovlyatKodUnitaPosleZapuska,
        private UmeetObnovlyatKodUnitaPosleZapuskaBezSbrosaPodgotovki $umeetObnovlyatKodUnitaPosleZapuskaBezSbrosaPodgotovki,
        private UnitmanSecurityService $securityService
    )
    {
    }
    function handle(ObnovitKodUnitaPosleZapuska $command, bool $prinuditenlniiSbrosPodgotovki = false): void
    {
        $unit = $this->unitRepository->getById($command->id);
        $project = $this->projectRepository->getById($unit->getProjectId());
        $projectUser = $project->getProjectUserById($this->securityService->getCurrentUserId());
        $unit->proverkaPrav($projectUser);

        if (!$project->poluchitNastroikiHuka()->obnovlenieBezSbrosaPodgotovki || $prinuditenlniiSbrosPodgotovki) {
            $zapushen = $unit->esliZapushen();
            $podgotovlen = $unit->esliPodgotovlen();
            $jobId = $this->umeetObnovlyatKodUnitaPosleZapuska->obnovitKodUnita($unit->getId(), $zapushen, $podgotovlen);
        } else {
            $jobId = $this->umeetObnovlyatKodUnitaPosleZapuskaBezSbrosaPodgotovki->obnovitKodUnita($unit->getId());
        }

        $unit->nachatObnovlenieUnitaPosleZapuska($jobId);
        $this->unitRepository->save($unit);
    }

    function handleSystem(ObnovitKodUnitaPosleZapuska $command): void
    {
        $unit = $this->unitRepository->getById($command->id);
        $project = $this->projectRepository->getById($unit->getProjectId());
        if (!$project->poluchitNastroikiHuka()->obnovlenieBezSbrosaPodgotovki) {
            $zapushen = $unit->esliZapushen();
            $podgotovlen = $unit->esliPodgotovlen();
            $jobId = $this->umeetObnovlyatKodUnitaPosleZapuska->obnovitKodUnita($unit->getId(), $zapushen, $podgotovlen);
        } else {
            $jobId = $this->umeetObnovlyatKodUnitaPosleZapuskaBezSbrosaPodgotovki->obnovitKodUnita($unit->getId());
        }

        $unit->nachatObnovlenieUnitaPosleZapuska($jobId);
        $this->unitRepository->save($unit);
    }
}
