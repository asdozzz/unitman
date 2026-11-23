<?php

declare(strict_types=1);

namespace App\Runner\Business\UseCase;

use App\Runner\Business\Port\RunnerRepository;
use App\Runner\Business\ReadModel\UgrozyRannerovReadModel;
use App\Runner\Business\Utils\UgrozyRannerov;

/**
 * Сводка угроз по раннерам для шапки сайта.
 *
 * Доступна всем авторизованным пользователям: отдаёт только уровни и
 * идентификаторы проблемных раннеров, без деталей ресурсов.
 */
final class PoluchitUgrozyRannerovQuery
{
    public function __construct(private RunnerRepository $runnerRepository)
    {
    }

    public function handle(): UgrozyRannerovReadModel
    {
        $runners = [];
        $levels = [];

        foreach ($this->runnerRepository->getAll() as $state) {
            $level = UgrozyRannerov::uroven($state);
            $levels[] = $level;

            if (!UgrozyRannerov::estUgroza($level)) {
                continue;
            }

            $runners[] = [
                'id' => $state->getId(),
                'level' => $level,
                'blocked' => $state->isResourcesBlocked(),
                'healthFresh' => $state->isHealthFresh(),
            ];
        }

        // Проблемные раннеры — первыми.
        usort($runners, static function (array $left, array $right): int {
            $byLevel = UgrozyRannerov::rank($right['level']) <=> UgrozyRannerov::rank($left['level']);

            return $byLevel !== 0 ? $byLevel : strcmp($left['id'], $right['id']);
        });

        return new UgrozyRannerovReadModel(
            $runners !== [],
            UgrozyRannerov::hudshiy($levels),
            count($runners),
            $runners,
        );
    }
}
