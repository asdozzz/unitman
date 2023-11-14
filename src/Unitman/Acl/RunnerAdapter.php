<?php

namespace App\Unitman\Acl;
use App\Runner\Api\RunnerApi;
use App\Runner\Business\Command\InitProjectCommand;
use App\Runner\Business\Command\NachatSborkuUnita;
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
use App\Unitman\Business\Port\RepoRepository;
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

    public function poluchitResultatSborki(Unit $unit): ResultatSborkiUnita
    {
        $result = $this->runnerApi->poluchitResultatSborki($unit->getBuildWorkflowId());

        if (empty($result)) {
            throw new \Exception('runner.sborka_eshe_ne_zakonchena');
        }

        return new ResultatSborkiUnita($result->Success, $result->Message, $result->Config);
    }

    public function nachatPodgotovkuUnita(Unit $unit): JobId
    {

    }

    public function nachatObnovlenieUnita(Unit $unit): JobId
    {
        // TODO: Implement nachatObnovlenieUnita() method.
    }

    public function nachatSbrosPodgotovkiUnita(Unit $unit): JobId
    {
        // TODO: Implement nachatSbrosPodgotovkiUnita() method.
    }

    public function nachatZapuskUnita(Unit $unit): JobId
    {
        // TODO: Implement nachatZapuskUnita() method.
    }

    public function nachatOstanovkuUnita(Unit $unit): JobId
    {
        // TODO: Implement nachatOstanovkuUnita() method.
    }

    public function nachatUdalenieUnita(Unit $unit): JobId
    {
        // TODO: Implement nachatUdalenieUnita() method.
    }



    public function poluchitResultatPodgotovki(Unit $unit): ResultatPodgotovkiUnita
    {
        // TODO: Implement poluchitResultatPodgotovki() method.
    }

    public function poluchitResultatObnovleniyaUnita(Unit $unit): ResultatObnovleniyaUnita
    {
        // TODO: Implement poluchitResultatObnovleniyaUnita() method.
    }

    public function poluchitResultatSbrosaPodgotovkiUnita(Unit $unit): ResultatSbrosaPodgotovkiUnita
    {
        // TODO: Implement poluchitResultatSbrosaPodgotovkiUnita() method.
    }

    public function poluchitResultatZapuskaUnita(Unit $unit): ResultatZapuskaUnita
    {
        // TODO: Implement poluchitResultatZapuskaUnita() method.
    }

    public function poluchitResultatOstanovkiUnita(Unit $unit): ResultatOstanovkiUnita
    {
        // TODO: Implement poluchitResultatOstanovkiUnita() method.
    }

    public function poluchitResultatUdaleniyaUnita(Unit $unit): ResultatUdaleniyaUnita
    {
        // TODO: Implement poluchitResultatUdaleniyaUnita() method.
    }
}
