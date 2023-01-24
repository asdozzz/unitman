<?php

namespace App\Unitman\Business\UseCase;

use App\Unitman\Business\Command\AddApplicationCommand;
use App\Unitman\Business\Model\Application;
use App\Unitman\Business\Model\Application\ApplicationId;
use App\Unitman\Business\Port\ApplicationRepository;
use App\Unitman\Business\Port\CanGeneateGuid;

final class AddApplicationUseCase
{
    public function __construct(private CanGeneateGuid $canGeneateGuid, private ApplicationRepository $applicationRepository)
    {
    }

    function handle(AddApplicationCommand $command): void
    {
        $appId = new ApplicationId($this->canGeneateGuid->makeGuid());
        $app = new Application($appId, new Application\ApplicationName($command->getName()));
        $this->applicationRepository->save($app);
    }
}
