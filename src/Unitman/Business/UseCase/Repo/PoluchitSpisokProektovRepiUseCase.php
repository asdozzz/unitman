<?php

namespace App\Unitman\Business\UseCase\Repo;

use App\Unitman\Business\Command\Repo\PoluchitSpisokProektovRepi;
use App\Unitman\Business\Port\Repo\RepoRepository;
use App\Unitman\Business\Port\Repo\UmeetPoluchatSpisokProektovHranilisha;
use App\Unitman\Business\ReadModel\ProektHranilisha;

final class PoluchitSpisokProektovRepiUseCase
{
    public function __construct(
        private RepoRepository $repoRepository,
        private UmeetPoluchatSpisokProektovHranilisha $umeetPoluchatSpisokProektovHranilisha
    )
    {
    }

    /**
     * @return ProektHranilisha[]
     * */
    function handle(PoluchitSpisokProektovRepi $command): array
    {
        $repo = $this->repoRepository->getById($command->id);
        return $this->umeetPoluchatSpisokProektovHranilisha->poluchitSpisokProektovHranilisha($repo, $command->query);
    }
}
