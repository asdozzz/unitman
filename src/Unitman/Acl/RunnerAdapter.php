<?php

namespace App\Unitman\Acl;
use App\Runner\Api\RunnerApi;
use App\Runner\Business\Command\InitProjectCommand;
use App\Runner\Business\Command\NachatIzmenenieVetkiUnita;
use App\Runner\Business\Command\NachatObnovlenieUnita;
use App\Runner\Business\Command\NachatOchistkuProekta;
use App\Runner\Business\Command\NachatOstanovkuUnita;
use App\Runner\Business\Command\NachatPodgotovkuUnita;
use App\Runner\Business\Command\NachatSborkuUnita;
use App\Runner\Business\Command\NachatSbrosPodgotovkiUnita;
use App\Runner\Business\Command\NachatUdalenieUnita;
use App\Runner\Business\Command\NachatZapuskUnita;
use App\Runner\Business\Command\RemoveProjectCommand;
use App\Runner\Business\Model\GolangRunner\Unit\Step;
use App\Unitman\Business\Model\Project;
use App\Unitman\Business\Model\Runner\JobId;
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
        private readonly RunnerApi $runnerApi,
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

    public function nachatSborkuUnita(Unit $unit): JobId
    {
        $project = $this->projectEventsRepository->getById($unit->getProjectId());
        $storageUrl = $this->getProjectUrl($project);
        $command = new NachatSborkuUnita($unit->getProjectId(), $unit->getId() ,$unit->getName(), $storageUrl, $unit->getBranch());
        $workflowId = $this->runnerApi->nachatSborkuUnita($command);
        return new JobId($workflowId);
    }
    public function nachatPodgotovkuUnita(Unit $unit): JobId
    {
        $project = $this->projectEventsRepository->getById($unit->getProjectId());
        $storageUrl = $this->getProjectUrl($project);
        $variables = $this->makeVariablesListFromUnit($unit, $project);
        $command = new NachatPodgotovkuUnita($unit->getProjectId(), $project->getName() ,$unit->getId() ,$unit->getName(), $storageUrl, $unit->poluchitKomandiPodgotovki(), $variables);
        $workflowId = $this->runnerApi->nachatPodgotovkuUnita($command);
        return new JobId($workflowId);
    }

    public function nachatObnovlenieUnita(Unit $unit): JobId
    {
        $project = $this->projectEventsRepository->getById($unit->getProjectId());
        $storageUrl = $this->getProjectUrl($project);
        $command = new NachatObnovlenieUnita($unit->getProjectId(), $unit->getId() ,$unit->getName(), $storageUrl);
        $workflowId = $this->runnerApi->nachatObnovlenieUnita($command);
        return new JobId($workflowId);
    }

    public function nachatSbrosPodgotovkiUnita(Unit $unit): JobId
    {
        $project = $this->projectEventsRepository->getById($unit->getProjectId());
        $storageUrl = $this->getProjectUrl($project);
        $variables = $this->makeVariablesListFromUnit($unit, $project);
        $command = new NachatSbrosPodgotovkiUnita($unit->getId(), $unit->getProjectId(), $unit->getName(), $storageUrl, $unit->poluchitKomandiSbrosaPodgotovki(), $variables);
        $workflowId = $this->runnerApi->nachatSbrosPodgotovkiUnita($command);
        return new JobId($workflowId);
    }

    public function nachatZapuskUnita(Unit $unit, Project $project): JobId
    {
        $project = $this->projectEventsRepository->getById($unit->getProjectId());
        $storageUrl = $this->getProjectUrl($project);
        $variables = $this->makeVariablesListFromUnit($unit, $project);
        $command = new NachatZapuskUnita($unit->getProjectId(), $project->getName(),$unit->getId() ,$unit->getName(), $storageUrl, $unit->poluchitKomandiZapuska(), $variables);
        $workflowId = $this->runnerApi->nachatZapuskUnita($command);
        return new JobId($workflowId);
    }

    public function nachatOstanovkuUnita(Unit $unit, Project $project): JobId
    {
        $project = $this->projectEventsRepository->getById($unit->getProjectId());
        $storageUrl = $this->getProjectUrl($project);
        $variables = $this->makeVariablesListFromUnit($unit, $project);
        $command = new NachatOstanovkuUnita($unit->getProjectId(), $project->getName(),$unit->getId() ,$unit->getName(), $storageUrl, $unit->poluchitKomandiOstanovki(), $variables);
        $workflowId = $this->runnerApi->nachatOstanovkuUnita($command);
        return new JobId($workflowId);
    }

    public function nachatUdalenieUnita(Unit $unit): JobId
    {
        $project = $this->projectEventsRepository->getById($unit->getProjectId());
        $storageUrl = $this->getProjectUrl($project);
        $command = new NachatUdalenieUnita($unit->getProjectId(), $unit->getId() ,$unit->getName(), $storageUrl);
        $workflowId = $this->runnerApi->nachatUdalenieUnita($command);
        return new JobId($workflowId);
    }

    public function poluchitResultatSborki(Unit $unit): ResultatSborkiUnita
    {
        $result = $this->runnerApi->poluchitResultatSborki($unit->poluchitWorkflowIdDlySborki());

        if (empty($result)) {
            throw new \Exception('runner.sborka_eshe_ne_zakonchena');
        }

        return new ResultatSborkiUnita((bool)$result->Success, $this->convertRunnerSteps($result->Steps), $result->Config);
    }

    public function poluchitResultatObnovleniyaUnita(Unit $unit): ResultatObnovleniyaUnita
    {
        $result = $this->runnerApi->poluchitResultatObnovleniyaUnita($unit->poluchitWorkflowIdDlyObnovleniya());

        if (empty($result)) {
            throw new \Exception('runner.obnovlenie_eshe_ne_zakoncheno');
        }

        return new ResultatObnovleniyaUnita((bool) $result->Success, $this->convertRunnerSteps($result->Steps), $result->Config);
    }

    public function poluchitResultatPodgotovki(Unit $unit): ResultatPodgotovkiUnita
    {
        $result = $this->runnerApi->poluchitResultatPodgotovki($unit->poluchitWorkflowIdDlyPodgotovki());

        if (empty($result)) {
            throw new \Exception('runner.podgotovka_eshe_ne_zakonchena');
        }

        return new ResultatPodgotovkiUnita((bool) $result->Success, $this->convertRunnerSteps($result->Steps));
    }


    public function poluchitResultatSbrosaPodgotovkiUnita(Unit $unit): ResultatSbrosaPodgotovkiUnita
    {
        $result = $this->runnerApi->poluchitResultatSbrosaPodgotovkiUnita($unit->poluchitWorkflowIdDlySbrosaPodgotovki());

        if (empty($result)) {
            throw new \Exception('runner.sbros_podgotovki_eshe_ne_zakonchen');
        }

        return new ResultatSbrosaPodgotovkiUnita((bool) $result->Success, $this->convertRunnerSteps($result->Steps));
    }

    public function poluchitResultatZapuskaUnita(Unit $unit): ResultatZapuskaUnita
    {
        $result = $this->runnerApi->poluchitResultatZapuskaUnita($unit->poluchitWorkflowIdDlyZapuska());

        if (empty($result)) {
            throw new \Exception('runner.zapusk_eshe_ne_zakonchen');
        }

        return new ResultatZapuskaUnita((bool) $result->Success, $this->convertRunnerSteps($result->Steps));
    }

    public function poluchitResultatOstanovkiUnita(Unit $unit): ResultatOstanovkiUnita
    {
        $result = $this->runnerApi->poluchitResultatOstanovkiUnita($unit->poluchitWorkflowIdDlyOstanovki());

        if (empty($result)) {
            throw new \Exception('runner.ostanovka_eshe_ne_zakonchena');
        }

        return new ResultatOstanovkiUnita((bool) $result->Success, $this->convertRunnerSteps($result->Steps));
    }

    public function poluchitResultatUdaleniyaUnita(Unit $unit): ResultatUdaleniyaUnita
    {
        $result = $this->runnerApi->poluchitResultatUdaleniyaUnita($unit->poluchitWorkflowIdDlyUdaleniya());

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
        $variables = array_map(fn(Unit\VariableValue $variableValue) => array('Id' => $variableValue->getId(), 'Value' => $variableValue->getValue()), $unit->poluchitZnacheniyaPeremenih());

        foreach ($project->poluchitPeremenieProekta() as $projectVariable) {
            $variables[] = array('Id' => 'PV_'.$projectVariable->code, 'Value' => $projectVariable->value);
        }

        return $variables;
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

    public function poluchitResultatIzmeneniyaVetki(Unit $unit): ResultatIzmeneniyaVetkiUnita
    {
        $result = $this->runnerApi->poluchitResultatIzmeneniyaVetki($unit->poluchitWorkflowIdDlyIzmeneniya());

        if (empty($result)) {
            throw new \Exception('runner.izmenenie_vetki_eshe_ne_zakoncheno');
        }

        return new ResultatIzmeneniyaVetkiUnita((bool) $result->Success, $this->convertRunnerSteps($result->Steps), $result->Config);
    }

    public function nachatIzmenenieVetkiUnita(Unit $unit, string $newBranch): JobId
    {
        $project = $this->projectEventsRepository->getById($unit->getProjectId());
        $storageUrl = $this->getProjectUrl($project);
        $command = new NachatIzmenenieVetkiUnita($unit->getProjectId(), $unit->getId() ,$unit->getName(), $storageUrl, $newBranch);
        $workflowId = $this->runnerApi->nachatIzmenenieVetkiUnita($command);
        return new JobId($workflowId);
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
}
