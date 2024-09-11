<?php

namespace App\Unitman\Business\UseCase\Project;

use App\Unitman\Business\Command\Project\DobavitSobitieIzHranilisha;
use App\Unitman\Business\Model\SobitieIzHranilisha;
use App\Unitman\Business\Port\CanGeneateGuid;
use App\Unitman\Infra\Repository\Project\SobitieIzHranilishaRepository;

final class DobavitSobitieIzHranilishaUseCase
{
    public function __construct(private SobitieIzHranilishaRepository $repository, private CanGeneateGuid $canGeneateGuid)
    {
    }

    function handle(DobavitSobitieIzHranilisha $command): string
    {
        $id = $this->canGeneateGuid->makeGuid();
        $model = new SobitieIzHranilisha($id, $command->projectId, $command->payload);
        $this->repository->insert($model);

        return $model->id;
    }
}
