<?php

declare(strict_types=1);

namespace App\Mediateka\Business\UseCase;

use App\Mediateka\Business\Model\FaylUnita;
use App\Mediateka\Business\Port\FaylUnitaRepository;

final class PoluchitFaylUnitaQuery
{
    public function __construct(private readonly FaylUnitaRepository $faylUnitaRepository)
    {
    }

    public function handle(string $fileId): ?FaylUnita
    {
        return $this->faylUnitaRepository->findById($fileId);
    }
}
