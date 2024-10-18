<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\ObnovitStatistikuPoKontaineruUnita;
use App\Unitman\Business\Port\Unit\UnitRepository;

final class ObnovitStatistikuPoKonteineruUnitaUseCase
{
    public function __construct(private UnitRepository $unitRepository)
    {
    }

    function handle(ObnovitStatistikuPoKontaineruUnita $command, int $try = 0): void
    {
        $unit = $this->unitRepository->getById($command->id);

        if (!$unit->isWaitResultFromRunner()) {
            $unit->onbovitStatistikuKonteinera($command);
            $this->unitRepository->save($unit);
        }
    }
}
