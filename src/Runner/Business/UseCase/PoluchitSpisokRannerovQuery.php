<?php

declare(strict_types=1);

namespace App\Runner\Business\UseCase;

use App\Runner\Business\Port\RunnerRepository;
use App\Runner\Business\Port\UmeetProveryatPravaAdmina;
use App\Runner\Business\ReadModel\RannerKartaReadModel;
use App\Runner\Business\Utils\UgrozyRannerov;

/**
 * Список раннеров с состоянием ресурсов. Доступно только администратору.
 */
final class PoluchitSpisokRannerovQuery
{
    public function __construct(
        private UmeetProveryatPravaAdmina $security,
        private RunnerRepository $runnerRepository,
    ) {
    }

    /**
     * @return RannerKartaReadModel[]
     */
    public function handle(): array
    {
        if (!$this->security->estAdministrator()) {
            throw new \Exception('security.access_denied');
        }

        $karty = [];

        foreach ($this->runnerRepository->getAll() as $state) {
            $resources = $state->getResources();

            $karty[] = new RannerKartaReadModel(
                $state->getId(),
                $state->getTaskQueue(),
                $state->isActive(),
                $state->isHealthFresh(),
                $state->getUnixtimeLastHealth(),
                UgrozyRannerov::uroven($state),
                $state->isResourcesBlocked(),
                $resources?->blockReason,
                $resources,
                $state->getMemoryInfo(),
            );
        }

        // Самые проблемные раннеры — первыми.
        usort($karty, static function (RannerKartaReadModel $left, RannerKartaReadModel $right): int {
            $byLevel = UgrozyRannerov::rank($right->level) <=> UgrozyRannerov::rank($left->level);

            return $byLevel !== 0 ? $byLevel : strcmp($left->id, $right->id);
        });

        return $karty;
    }
}
