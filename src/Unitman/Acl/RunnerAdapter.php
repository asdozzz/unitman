<?php

namespace App\Unitman\Acl;
use App\Runner\Api\RunnerApiInterface;
use App\Runner\Business\Command\InitProjectCommand;
use App\Runner\Business\Command\NachatDeistvieUnita;
use App\Runner\Business\Command\NachatIzmenenieVetkiUnita;
use App\Runner\Business\Command\NachatObnovlenieUnita;
use App\Runner\Business\Command\NachatOchistkuProekta;
use App\Runner\Business\Command\NachatOstanovkuUnita;
use App\Runner\Business\Command\NachatPodgotovkuUnita;
use App\Runner\Business\Command\NachatSborkuUnita;
use App\Runner\Business\Command\NachatSbrosPodgotovkiUnita;
use App\Runner\Business\Command\NachatUdalenieUnita;
use App\Runner\Business\Command\NachatZapuskUnita;
use App\Runner\Business\Command\ProveritKonteinerUnita;
use App\Runner\Business\Command\RemoveProjectCommand;
use App\Runner\Business\Model\GolangRunner\Unit\Step;
use App\Unitman\Business\Model\Project;
use App\Unitman\Business\Model\Runner\JobId;
use App\Unitman\Business\Model\Runner\ResultatDeistviyaiUnita;
use App\Unitman\Business\Model\Runner\ResultatIzmeneniyaVetkiUnita;
use App\Unitman\Business\Model\Runner\ResultatObnovleniyaUnita;
use App\Unitman\Business\Model\Runner\ResultatOshistkiProekta;
use App\Unitman\Business\Model\Runner\ResultatOstanovkiUnita;
use App\Unitman\Business\Model\Runner\ResultatPodgotovkiUnita;
use App\Unitman\Business\Model\Runner\ResultatSborkiProekta;
use App\Unitman\Business\Model\Runner\ResultatSborkiUnita;
use App\Unitman\Business\Model\Runner\ResultatSbrosaPodgotovkiUnita;
use App\Unitman\Business\Model\Runner\ResultatUdaleniyaProekta;
use App\Unitman\Business\Model\Runner\ResultatUdaleniyaUnita;
use App\Unitman\Business\Model\Runner\ResultatZapuskaUnita;
use App\Unitman\Business\Model\Unit;
use App\Unitman\Business\Port\Repo\RepoRepository;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Infra\Adapter\StorageApiAdapterFactory;
use App\Unitman\Infra\Repository\Project\SqlProjectEventsRepository;

final class RunnerAdapter implements RunnerService
{
    public function __construct(
        private readonly RunnerApiInterface $runnerApi,
        private readonly RepoRepository $repoRepository,
        private SqlProjectEventsRepository $projectEventsRepository,
        private StorageApiAdapterFactory $storageApiAdapterFactory
    )
    {
    }

    /**
     * @param array $Steps
     *
     * @return array<Unit\Runner\RunnerJobStep>
     * */
    private function convertRunnerSteps(array $Steps): array
    {
        return array_map(fn(array $step) => new Unit\Runner\RunnerJobStep($step['Command'], $step['Response'], $step['Success'], $step['Unixtime']), $Steps);
    }

    public function nachatSborkuProekta(Project $project): JobId
    {
        $projectUrl = $this->getProjectUrl($project);
        $command = new InitProjectCommand($project->getId(), $project->getMainBranchName(), $projectUrl);
        $workflowId = $this->runnerApi->initProject($command);
        return new JobId($workflowId);
    }

    public function nachatUdalenieProekta(Project $project): JobId
    {
        $command = new RemoveProjectCommand($project->getId());
        $workflowId = $this->runnerApi->removeProject($command);
        return new JobId($workflowId);
    }

    public function nachatOchistkuProekta(Project $project): JobId
    {
        $projectUrl = $this->getProjectUrl($project);
        $command = new NachatOchistkuProekta($project->getId(), $project->getName(), $projectUrl);
        $workflowId = $this->runnerApi->ochistitProekt($command);
        return new JobId($workflowId);
    }

    public function nachatSborkuUnita(string $jobId, Unit $unit): JobId
    {
        $project = $this->projectEventsRepository->getById($unit->getProjectId());
        $storageUrl = $this->getProjectUrl($project);
        $command = new NachatSborkuUnita($unit->getProjectId(), $unit->getId() ,$unit->getName(), $storageUrl, $unit->getBranch());
        $workflowId = $this->runnerApi->nachatSborkuUnita($jobId,$command);
        return new JobId($workflowId);
    }
    public function nachatPodgotovkuUnita(string $jobId, Unit $unit): JobId
    {
        $project = $this->projectEventsRepository->getById($unit->getProjectId());
        $storageUrl = $this->getProjectUrl($project);
        $variables = $this->makeVariablesListFromUnit($unit, $project);
        $caches = $this->makeCachesListFromUnit($unit);
        $command = new NachatPodgotovkuUnita($unit->getProjectId(), $project->getName() ,$unit->getId() ,$unit->getName(), $storageUrl, $unit->poluchitKomandiPodgotovki(), $variables, $caches);
        $workflowId = $this->runnerApi->nachatPodgotovkuUnita($jobId,$command);
        return new JobId($workflowId);
    }

    public function nachatObnovlenieUnita(string $jobId, Unit $unit): JobId
    {
        $project = $this->projectEventsRepository->getById($unit->getProjectId());
        $storageUrl = $this->getProjectUrl($project);
        $command = new NachatObnovlenieUnita($unit->getProjectId(), $unit->getId() ,$unit->getName(), $storageUrl);
        $workflowId = $this->runnerApi->nachatObnovlenieUnita($jobId, $command);
        return new JobId($workflowId);
    }

    public function nachatSbrosPodgotovkiUnita(string $jobId, Unit $unit): JobId
    {
        $project = $this->projectEventsRepository->getById($unit->getProjectId());
        $storageUrl = $this->getProjectUrl($project);
        $variables = $this->makeVariablesListFromUnit($unit, $project);
        $command = new NachatSbrosPodgotovkiUnita($unit->getId(), $unit->getProjectId(), $unit->getName(), $storageUrl, $unit->poluchitKomandiSbrosaPodgotovki(), $variables);
        $workflowId = $this->runnerApi->nachatSbrosPodgotovkiUnita($jobId, $command);
        return new JobId($workflowId);
    }

    public function nachatZapuskUnita(string $jobId, Unit $unit, Project $project): JobId
    {
        $project = $this->projectEventsRepository->getById($unit->getProjectId());
        $storageUrl = $this->getProjectUrl($project);
        $variables = $this->makeVariablesListFromUnit($unit, $project);
        $caches = $this->makeCachesListFromUnit($unit);
        $command = new NachatZapuskUnita($unit->getProjectId(), $project->getName(),$unit->getId() ,$unit->getName(), $storageUrl, $unit->poluchitKomandiZapuska(), $variables, $caches);
        $workflowId = $this->runnerApi->nachatZapuskUnita($jobId, $command);
        return new JobId($workflowId);
    }


    public function nachatVipolnenieDeistviya(string $jobId, Unit $unit, Project $project, string $actionId, array $values): JobId
    {
        $project = $this->projectEventsRepository->getById($unit->getProjectId());
        $konfigDeistviya = $unit->poluchitKonfigDeistviya($actionId);
        $variables = [];
        foreach ($values as $key => $value) {
            $konfigVariable = $konfigDeistviya->getConfigVariableById($key);
            $variables[] = array('Id' => $key, 'Value' => $value, 'Type' => $konfigVariable->getType());
        }

        $command = new NachatDeistvieUnita($unit->getProjectId(), $project->getName(),$unit->getId() ,$unit->getName(), $konfigDeistviya->commands, $variables);
        $workflowId = $this->runnerApi->nachatDeistvieUnita($jobId, $command);
        return new JobId($workflowId);
    }


    public function nachatOstanovkuUnita(string $jobId, Unit $unit, Project $project): JobId
    {
        $project = $this->projectEventsRepository->getById($unit->getProjectId());
        $storageUrl = $this->getProjectUrl($project);
        $variables = $this->makeVariablesListFromUnit($unit, $project);
        $command = new NachatOstanovkuUnita($unit->getProjectId(), $project->getName(),$unit->getId() ,$unit->getName(), $storageUrl, $unit->poluchitKomandiOstanovki(), $variables);
        $workflowId = $this->runnerApi->nachatOstanovkuUnita($jobId,$command);
        return new JobId($workflowId);
    }

    public function nachatUdalenieUnita(string $jobId, Unit $unit): JobId
    {
        $project = $this->projectEventsRepository->getById($unit->getProjectId());
        $storageUrl = $this->getProjectUrl($project);
        $command = new NachatUdalenieUnita($unit->getProjectId(), $unit->getId() ,$unit->getName(), $storageUrl);
        $workflowId = $this->runnerApi->nachatUdalenieUnita($jobId, $command);
        return new JobId($workflowId);
    }

    public function poluchitResultatSborki(string $jobId): ResultatSborkiUnita
    {
        $result = $this->runnerApi->poluchitResultatSborki($jobId);

        if (empty($result)) {
            throw new \Exception('runner.sborka_eshe_ne_zakonchena');
        }

        return new ResultatSborkiUnita((bool)$result->Success, $this->convertRunnerSteps($result->Steps), $result->Config);
    }

    public function poluchitResultatObnovleniyaUnita(string $jobId): ResultatObnovleniyaUnita
    {
        $result = $this->runnerApi->poluchitResultatObnovleniyaUnita($jobId);

        if (empty($result)) {
            throw new \Exception('runner.obnovlenie_eshe_ne_zakoncheno');
        }

        return new ResultatObnovleniyaUnita((bool) $result->Success, $this->convertRunnerSteps($result->Steps), $result->Config);
    }

    public function poluchitResultatPodgotovki(string $jobId): ResultatPodgotovkiUnita
    {
        $result = $this->runnerApi->poluchitResultatPodgotovki($jobId);

        if (empty($result)) {
            throw new \Exception('runner.podgotovka_eshe_ne_zakonchena');
        }

        return new ResultatPodgotovkiUnita((bool) $result->Success, $this->convertRunnerSteps($result->Steps));
    }


    public function poluchitResultatSbrosaPodgotovkiUnita(string $jobId): ResultatSbrosaPodgotovkiUnita
    {
        $result = $this->runnerApi->poluchitResultatSbrosaPodgotovkiUnita($jobId);

        if (empty($result)) {
            throw new \Exception('runner.sbros_podgotovki_eshe_ne_zakonchen');
        }

        return new ResultatSbrosaPodgotovkiUnita((bool) $result->Success, $this->convertRunnerSteps($result->Steps));
    }

    public function poluchitResultatZapuskaUnita(string $jobId): ResultatZapuskaUnita
    {
        $result = $this->runnerApi->poluchitResultatZapuskaUnita($jobId);

        if (empty($result)) {
            throw new \Exception('runner.zapusk_eshe_ne_zakonchen');
        }

        return new ResultatZapuskaUnita((bool) $result->Success, $this->convertRunnerSteps($result->Steps));
    }

    public function poluchitResultatOstanovkiUnita(string $jobId): ResultatOstanovkiUnita
    {
        $result = $this->runnerApi->poluchitResultatOstanovkiUnita($jobId);

        if (empty($result)) {
            throw new \Exception('runner.ostanovka_eshe_ne_zakonchena');
        }

        return new ResultatOstanovkiUnita((bool) $result->Success, $this->convertRunnerSteps($result->Steps));
    }

    public function poluchitResultatUdaleniyaUnita(string $jobId): ResultatUdaleniyaUnita
    {
        $result = $this->runnerApi->poluchitResultatUdaleniyaUnita($jobId);

        if (empty($result)) {
            throw new \Exception('runner.udalenie_eshe_ne_zakoncheno');
        }

        return new ResultatUdaleniyaUnita((bool) $result->Success, $this->convertRunnerSteps($result->Steps));
    }

    /**
     * @param Unit $unit
     * @return array|array[]
     */
    private function makeVariablesListFromUnit(Unit $unit, Project $project): array
    {
        $closure = fn(Unit\VariableValue $variableValue): array => array('Id' => $variableValue->getId(), 'Value' => $variableValue->getValue(), 'Type' => $variableValue->getType());
        $variables = array_map($closure, $unit->poluchitZnacheniyaPeremenih());

        foreach ($project->poluchitPeremenieProekta() as $projectVariable) {
            $variables[] = array('Id' => 'PV_'.$projectVariable->code, 'Value' => $projectVariable->value, 'Type' => 'string');
        }

        return $variables;
    }

    private function makeCachesListFromUnit(Unit $unit): array
    {
        $poluchitKonfigServisov = $unit->poluchitKonfigServisov();

        $result = [];

        foreach ($poluchitKonfigServisov as $konfigServisa) {
            if (empty($konfigServisa->cache)) {
                continue;
            }

            $result[] = array(
                'ServiceName' => $konfigServisa->name,
                'Keys' => $konfigServisa->cache->files,
                'Paths' => $konfigServisa->cache->paths
            );
        }

        return $result;
    }

    /**
     * @param Project $project
     * @return string
     */
    private function getProjectUrl(Project $project): string
    {
        $repo = $this->repoRepository->getById($project->getRepoId());
        $projectUrl = $this->storageApiAdapterFactory->getUrlForInitProject($repo, $project);
        return $projectUrl;
    }

    public function poluchitResultatSborkiProekta(Project $project): ResultatSborkiProekta
    {
        $result = $this->runnerApi->poluchitResultatSborkiProekta($project->poluchitWorkflowIdDlySborki());

        if (empty($result)) {
            throw new \Exception('runner.sborka_proekta_eshe_ne_zakonchena');
        }

        return new ResultatSborkiProekta($result->Success, $this->convertRunnerSteps($result->Steps));
    }

    public function poluchitResultatUdaleniyaProekta(Project $project): ResultatUdaleniyaProekta
    {
        $result = $this->runnerApi->poluchitResultatUdaleniyaProekta($project->poluchitWorkflowIdDlyUdaleniya());

        if (empty($result)) {
            throw new \Exception('runner.udalenie_proekta_eshe_ne_zakonchena');
        }

        return new ResultatUdaleniyaProekta($result->Success, $this->convertRunnerSteps($result->Steps));
    }

    public function poluchitResultatOchistkiProekta(Project $project): ResultatOshistkiProekta
    {
        $result = $this->runnerApi->poluchitResultatOchistkiProekta($project->poluchitWorkflowIdDlyOchistki());

        if (empty($result)) {
            throw new \Exception('runner.ochistka_proekta_eshe_ne_zakonchena');
        }

        return new ResultatOshistkiProekta((bool) $result->Success, $this->convertRunnerSteps($result->Steps));
    }

    public function poluchitResultatDeistviya(string $jobId): ResultatDeistviyaiUnita
    {
        $result = $this->runnerApi->poluchitResultatDeistviya($jobId);

        if (empty($result)) {
            throw new \Exception('runner.deistvie_ne_zakonchena');
        }

        return new ResultatDeistviyaiUnita((bool) $result->Success, $this->convertRunnerSteps($result->Steps));
    }

    public function proveritKonteinerUnita(Unit $unit, Project $project): bool
    {
        $command = new ProveritKonteinerUnita($unit->getProjectId(), $project->getName(),$unit->getId() ,$unit->getName());
        $result = $this->runnerApi->proveritKonteinerUnita($command);

        if (empty($result)) {
            throw new \Exception('runner.oshibka_proverki_unita');
        }

        return (bool) $result->Success;
    }
}
