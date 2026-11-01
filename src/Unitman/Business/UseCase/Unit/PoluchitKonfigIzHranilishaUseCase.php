<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\PoluchitKonfigIzHranilisha;
use App\Unitman\Business\Model\Unit\ConfigUnita;
use App\Unitman\Business\Port\CanParseYaml;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\Project\UmeetPoluchatKonfigProekta;
use App\Unitman\Business\Port\Repo\RepoRepository;
use App\Unitman\Business\ReadModel\Unit\PeremenyaUnita;
use App\Unitman\Business\ReadModel\Unit\UnitVolume;

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

    /**
     * @return array{peremenie: array<int, array<string, mixed>>, volumes: array<int, array<string, mixed>>}
     */
    function handle(PoluchitKonfigIzHranilisha $command): array
    {
        $project = $this->projectRepository->getById($command->projectId);
        $repo = $this->repoRepository->getById($project->getRepoId());
        $configRaw = $this->umeetPoluchatKonfigProekta->poluchitKonfigIzHranilisha(
            $repo,
            $project->getCode(),
            $command->branch
        );

        if (empty($configRaw)) {
            return ['peremenie' => [], 'volumes' => []];
        }

        $configArray = $this->canParseYaml->parse($configRaw);
        $config = ConfigUnita::fromArray($configArray);

        $peremenie = [];
        foreach ($config->getVariables() as $variable) {
            $peremenie[] = (new PeremenyaUnita($variable, ""))->toArray();
        }

        // Тома объявлены в конфиге (`unitman_volumes`), но файлов у нового юнита ещё нет.
        $volumes = [];
        foreach ($config->getVolumes() as $volumeConfig) {
            $volumes[] = UnitVolume::fromConfig($volumeConfig)->toArray();
        }

        return ['peremenie' => $peremenie, 'volumes' => $volumes];
    }
}
