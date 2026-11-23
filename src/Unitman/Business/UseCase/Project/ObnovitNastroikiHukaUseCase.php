<?php

namespace App\Unitman\Business\UseCase\Project;

use App\Unitman\Business\Command\Project\ObnovitNastroikiHuka;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\UseCase\Unit\PrimenitOtlozhennieDeistviyaPoProektuUseCase;

final class ObnovitNastroikiHukaUseCase
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private PrimenitOtlozhennieDeistviyaPoProektuUseCase $primenitPoProektu,
    )
    {
    }

    function handle(ObnovitNastroikiHuka $command): void
    {
        $proekt = $this->projectRepository->getById($command->id);
        $byloVklucheno = $proekt->poluchitNastroikiHuka()->avtoobnovlenie;

        $proekt->obnovitNastrokiHuka($command);
        $this->projectRepository->save($proekt);

        if ($command->avtoobnovlenie && !$byloVklucheno) {
            $this->primenitPoProektu->handle($command->id);
        }
    }
}
