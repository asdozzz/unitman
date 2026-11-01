<?php

declare(strict_types=1);

namespace App\Mediateka\Business\UseCase;

use App\Mediateka\Business\Model\FaylUnita;
use App\Mediateka\Business\Port\FaylUnitaRepository;

final class PoluchitFaylyUnitaQuery
{
    public function __construct(private readonly FaylUnitaRepository $faylUnitaRepository)
    {
    }

    /**
     * @return FaylUnita[]
     */
    public function handle(string $unitId): array
    {
        return $this->faylUnitaRepository->najtiPoUnitu($unitId);
    }
}
