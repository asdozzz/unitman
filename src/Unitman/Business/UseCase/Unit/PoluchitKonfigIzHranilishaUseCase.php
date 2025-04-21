<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\PoluchitKonfigIzHranilisha;
use App\Unitman\Business\Model\Unit\ConfigUnita;
use App\Unitman\Business\Port\CanParseYaml;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\Project\UmeetPoluchatKonfigProekta;
use App\Unitman\Business\Port\Repo\RepoRepository;
use App\Unitman\Business\ReadModel\Unit\PeremenyaUnita;

final class PoluchitKonfigIzHranilishaUseCase
{
    public function __construct(
        private RepoRepository $repoRepository,
        private ProjectRepository $projectRepository,
        private CanParseYaml $canParseYaml,
        private UmeetPoluchatKonfigProekta $umeetPoluchatKonfigProekta
    )
    {
    }

    function handle(PoluchitKonfigIzHranilisha $command): array
    {
        $project = $this->projectRepository->getById($command->projectId);
        $repo = $this->repoRepository->getById($project->getRepoId());
        $configRaw = $this->umeetPoluchatKonfigProekta->poluchitKonfigIzHranilisha(
            $repo,
            $project->getCode(),
            $command->branch
        );

        if (empty($configRaw)) return [];

        $configArray = $this->canParseYaml->parse($configRaw);
        $config = ConfigUnita::fromArray($configArray);

        $result = [];
        foreach ($config->getVariables() as $variable) {
            $result[] = (new PeremenyaUnita($variable, ""))->toArray();
        }

        return $result;
    }
}
