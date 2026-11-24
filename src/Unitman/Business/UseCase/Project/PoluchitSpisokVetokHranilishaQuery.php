<?php

namespace App\Unitman\Business\UseCase\Project;

use App\Unitman\Business\Command\Project\PoluchitSpisokVetokHranilisha;
use App\Unitman\Business\Port\Project\UmeetPoluchatSpisokVetokProekta;
use App\Unitman\Business\Port\Repo\RepoRepository;
use App\Unitman\Business\ReadModel\VetkaProekta;

/**
 * Список веток проекта в хранилище по паре «репозиторий + код проекта».
 *
 * Используется формой добавления проекта: проект в Unitman ещё не создан,
 * поэтому ветки запрашиваются напрямую у хранилища (GitLab/GitHub).
 */
final class PoluchitSpisokVetokHranilishaQuery
{
    public function __construct(
        private UmeetPoluchatSpisokVetokProekta $umeetPoluchatSpisokVetokProekta,
        private RepoRepository $repoRepository,
    ) {
    }

    /**
     * @return VetkaProekta[]
     * */
    function handle(PoluchitSpisokVetokHranilisha $command): array
    {
        $repo = $this->repoRepository->getById($command->repoId);
        return $this->umeetPoluchatSpisokVetokProekta->poluchitVetkiProekta($repo, $command->projectCode, $command->query);
    }
}
