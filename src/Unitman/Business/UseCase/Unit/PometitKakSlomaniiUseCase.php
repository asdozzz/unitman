<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\PometitKakSlomanii;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;

final class PometitKakSlomaniiUseCase
{
    public function __construct(
        private UnitRepository $unitRepository,
        private UnitmanSecurityService $securityService
    )
    {
    }

    function handle(PometitKakSlomanii $command): void
    {
        if (!$this->securityService->isAdmin()) {
            throw new \RuntimeException('security.access_denied');
        }
        $unit = $this->unitRepository->getById($command->id);
        $unit->pometitKakSlomanii();
        $this->unitRepository->save($unit);
    }
}
