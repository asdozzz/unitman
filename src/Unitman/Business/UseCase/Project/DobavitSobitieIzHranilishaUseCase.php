<?php

namespace App\Unitman\Business\UseCase\Project;

use App\Unitman\Business\Command\Project\DobavitSobitieIzHranilisha;
use App\Unitman\Business\Model\SobitieIzHranilisha;
use App\Unitman\Business\Port\CanGeneateGuid;
use App\Unitman\Infra\Jobs\SobitieIzHranilishaJobsHandler;
use App\Unitman\Infra\Jobs\ZadachaDlyOcherediService;
use App\Unitman\Infra\Repository\Project\SobitieIzHranilishaRepository;

final class DobavitSobitieIzHranilishaUseCase
{
    public function __construct(private ZadachaDlyOcherediService $zadachaDlyOcherediService, private CanGeneateGuid $canGeneateGuid)
    {
    }

    function handle(DobavitSobitieIzHranilisha $command): string
    {
        $id = $this->canGeneateGuid->makeGuid();
        $model = new SobitieIzHranilisha($id, $command->projectId, $command->payload);
        $this->zadachaDlyOcherediService->dobavitZadachuVOchered(SobitieIzHranilishaJobsHandler::QUEUE_NAME, $model, 2);

        return $model->id;
    }
}
