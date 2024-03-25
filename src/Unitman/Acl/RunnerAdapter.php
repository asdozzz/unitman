<?php

namespace App\Unitman\Acl;
use App\Runner\Api\RunnerApi;
use App\Runner\Business\Command\InitProjectCommand;
use App\Runner\Business\Command\NachatObnovlenieUnita;
use App\Runner\Business\Command\NachatOstanovkuUnita;
use App\Runner\Business\Command\NachatPodgotovkuUnita;
use App\Runner\Business\Command\NachatSborkuUnita;
use App\Runner\Business\Command\NachatSbrosPodgotovkiUnita;
use App\Runner\Business\Command\NachatUdalenieUnita;
use App\Runner\Business\Command\NachatZapuskUnita;
use App\Runner\Business\Command\RemoveProjectCommand;
use App\Unitman\Business\Model\Project;
use App\Unitman\Business\Model\Runner\JobId;
use App\Unitman\Business\Model\Runner\ResultatObnovleniyaUnita;
use App\Unitman\Business\Model\Runner\ResultatOstanovkiUnita;
use App\Unitman\Business\Model\Runner\ResultatPodgotovkiUnita;
use App\Unitman\Business\Model\Runner\ResultatSborkiUnita;
use App\Unitman\Business\Model\Runner\ResultatSbrosaPodgotovkiUnita;
use App\Unitman\Business\Model\Runner\ResultatUdaleniyaUnita;
use App\Unitman\Business\Model\Runner\ResultatZapuskaUnita;
use App\Unitman\Business\Model\Unit;
use App\Unitman\Business\Port\Repo\RepoRepository;
use App\Unitman\Business\Port\RunnerService;

final class RunnerAdapter implements RunnerService
{
    public function __construct(private readonly RunnerApi $runnerApi, private readonly RepoRepository $repoRepository)
    {
    }

    public function buildProject(Project $project): Project\ProjectDataAboutBuilding
    {
        $repo = $this->repoRepository->getById($project->getRepoId());
        $storageUrl = $repo->getRepoUrlWithCredentials();
        $command = new InitProjectCommand($project->getId(), $project->getMainBranchName(), $storageUrl.'/'.$project->getCode().'.git');
        $initProjectResult = $this->runnerApi->initProject($command);

        return new Project\ProjectDataAboutBuilding('stub', true, $initProjectResult->Success, $initProjectResult->Message);
    }

    public function removeProject(Project $project): Project\ProjectDataAboutRemoving
    {
        $command = new RemoveProjectCommand($project->getId());
        $removeResult = $this->runnerApi->removeProject($command);

        return new Project\ProjectDataAboutRemoving('stub', true, $removeResult->Success, $removeResult->Message);
    }

    public function nachatSborkuUnita(Unit $unit): JobId
    {
        $command = new NachatSborkuUnita($unit->getProjectId(), $unit->getName(), $unit->getBranch());
        $workflowId = $this->runnerApi->nachatSborkuUnita($command);
        return new JobId($workflowId);
    }
    public function nachatPodgotovkuUnita(Unit $unit): JobId
    {
        $variables = $this->makeVariablesListFromUnit($unit);
        $command = new NachatPodgotovkuUnita($unit->getProjectId(), $unit->getName(), $unit->poluchitKomandiPodgotovki(), $variables);
        $workflowId = $this->runnerApi->nachatPodgotovkuUnita($command);
        return new JobId($workflowId);
    }

    public function nachatObnovlenieUnita(Unit $unit): JobId
    {
        $command = new NachatObnovlenieUnita($unit->getProjectId(), $unit->getName());
        $workflowId = $this->runnerApi->nachatObnovlenieUnita($command);
        return new JobId($workflowId);
    }

    public function nachatSbrosPodgotovkiUnita(Unit $unit): JobId
    {
        $variables = $this->makeVariablesListFromUnit($unit);
        $command = new NachatSbrosPodgotovkiUnita($unit->getId(), $unit->getProjectId(), $unit->getName(), $unit->poluchitKomandiSbrosaPodgotovki(), $variables);
        $workflowId = $this->runnerApi->nachatSbrosPodgotovkiUnita($command);
        return new JobId($workflowId);
    }

    public function nachatZapuskUnita(Unit $unit, Project $project): JobId
    {
        $variables = $this->makeVariablesListFromUnit($unit);
        $command = new NachatZapuskUnita($unit->getProjectId(), $project->getName(),$unit->getName(), $unit->poluchitKomandiZapuska(), $variables);
        $workflowId = $this->runnerApi->nachatZapuskUnita($command);
        return new JobId($workflowId);
    }

    public function nachatOstanovkuUnita(Unit $unit, Project $project): JobId
    {
        $variables = $this->makeVariablesListFromUnit($unit);
        $command = new NachatOstanovkuUnita($unit->getProjectId(), $project->getName(),$unit->getName(), $unit->poluchitKomandiOstanovki(), $variables);
        $workflowId = $this->runnerApi->nachatOstanovkuUnita($command);
        return new JobId($workflowId);
    }

    public function nachatUdalenieUnita(Unit $unit): JobId
    {
        $command = new NachatUdalenieUnita($unit->getProjectId(), $unit->getName());
        $workflowId = $this->runnerApi->nachatUdalenieUnita($command);
        return new JobId($workflowId);
    }

    public function poluchitResultatSborki(Unit $unit): ResultatSborkiUnita
    {
        $result = $this->runnerApi->poluchitResultatSborki($unit->poluchitWorkflowIdDlySborki());

        if (empty($result)) {
            throw new \Exception('runner.sborka_eshe_ne_zakonchena');
        }

        return new ResultatSborkiUnita((bool)$result->Success, $result->Message, $result->Config);
    }

    public function poluchitResultatObnovleniyaUnita(Unit $unit): ResultatObnovleniyaUnita
    {
        $result = $this->runnerApi->poluchitResultatObnovleniyaUnita($unit->poluchitWorkflowIdDlyObnovleniya());

        if (empty($result)) {
            throw new \Exception('runner.obnovlenie_eshe_ne_zakoncheno');
        }

        return new ResultatObnovleniyaUnita((bool) $result->Success, $result->Message, $result->Config);
    }

    public function poluchitResultatPodgotovki(Unit $unit): ResultatPodgotovkiUnita
    {
        $result = $this->runnerApi->poluchitResultatPodgotovki($unit->poluchitWorkflowIdDlyPodgotovki());

        if (empty($result)) {
            throw new \Exception('runner.podgotovka_eshe_ne_zakonchena');
        }

        return new ResultatPodgotovkiUnita((bool) $result->Success, $result->Message);
    }


    public function poluchitResultatSbrosaPodgotovkiUnita(Unit $unit): ResultatSbrosaPodgotovkiUnita
    {
        $result = $this->runnerApi->poluchitResultatSbrosaPodgotovkiUnita($unit->poluchitWorkflowIdDlySbrosaPodgotovki());

        if (empty($result)) {
            throw new \Exception('runner.sbros_podgotovki_eshe_ne_zakonchen');
        }

        return new ResultatSbrosaPodgotovkiUnita((bool) $result->Success, $result->Message);
    }

    public function poluchitResultatZapuskaUnita(Unit $unit): ResultatZapuskaUnita
    {
        $result = $this->runnerApi->poluchitResultatZapuskaUnita($unit->poluchitWorkflowIdDlyZapuska());

        if (empty($result)) {
            throw new \Exception('runner.zapusk_eshe_ne_zakonchen');
        }

        return new ResultatZapuskaUnita((bool) $result->Success, $result->Message);
    }

    public function poluchitResultatOstanovkiUnita(Unit $unit): ResultatOstanovkiUnita
    {
        $result = $this->runnerApi->poluchitResultatOstanovkiUnita($unit->poluchitWorkflowIdDlyOstanovki());

        if (empty($result)) {
            throw new \Exception('runner.ostanovka_eshe_ne_zakonchena');
        }

        return new ResultatOstanovkiUnita((bool) $result->Success, $result->Message);
    }

    public function poluchitResultatUdaleniyaUnita(Unit $unit): ResultatUdaleniyaUnita
    {
        $result = $this->runnerApi->poluchitResultatUdaleniyaUnita($unit->poluchitWorkflowIdDlyUdaleniya());

        if (empty($result)) {
            throw new \Exception('runner.udalenie_eshe_ne_zakoncheno');
        }

        return new ResultatUdaleniyaUnita((bool) $result->Success, $result->Message);
    }

    /**
     * @param Unit $unit
     * @return array|array[]
     */
    private function makeVariablesListFromUnit(Unit $unit): array
    {
        $variables = array_map(fn(Unit\VariableValue $variableValue) => array('Id' => $variableValue->getId(), 'Value' => $variableValue->getValue()), $unit->poluchitZnacheniyaPeremenih());
        return $variables;
    }
}
