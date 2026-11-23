<?php

declare(strict_types=1);

namespace App\Runner\Business\UseCase;

use App\Runner\Business\Port\RunnerRepository;
use App\Runner\Business\Port\UmeetProveryatPravaAdmina;
use App\Runner\Business\ReadModel\ResursiRunneraReadModel;

/**
 * Ресурсы сервера раннера по умолчанию. Доступно только администратору.
 */
final class PoluchitResursiDefoltnogoRunneraQuery
{
    public function __construct(
        private UmeetProveryatPravaAdmina $security,
        private RunnerRepository $runnerRepository,
    ) {
    }

    public function handle(): ResursiRunneraReadModel
    {
        if (!$this->security->estAdministrator()) {
            throw new \Exception('security.access_denied');
        }

        try {
            $state = $this->runnerRepository->getDefaultRunnerState();
        } catch (\Exception) {
            // Состояния ещё нет: раннер ни разу не присылал health-push.
            return new ResursiRunneraReadModel(false, false, 0, false, null);
        }

        return new ResursiRunneraReadModel(
            $state->isActive(),
            $state->isHealthFresh(),
            $state->getUnixtimeLastHealth(),
            $state->isResourcesBlocked(),
            $state->getResources(),
        );
    }
}
