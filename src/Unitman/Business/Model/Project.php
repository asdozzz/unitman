<?php

namespace App\Unitman\Business\Model;

use App\Unitman\Business\Command\Project\AddProject;
use App\Unitman\Business\Command\Project\AddUserToProject;
use App\Unitman\Business\Command\Project\DobavitPeremenuyuVProekt;
use App\Unitman\Business\Command\Project\IzmenitZnacheniePeremenoiProekta;
use App\Unitman\Business\Command\Project\ObnovitNastroikiHuka;
use App\Unitman\Business\Command\Project\RemoveUserFromProject;
use App\Unitman\Business\Command\Project\UdalitPeremenuyuIzProekta;
use App\Unitman\Business\Command\Project\UpdateProjectData;
use App\Unitman\Business\Model\Project\Event\NastroikiHukaProektaUstanovleni;
use App\Unitman\Business\Model\Project\Event\OchistkaProektaNachalas;
use App\Unitman\Business\Model\Project\Event\OshibkaOchistkiProektaUstanovlena;
use App\Unitman\Business\Model\Project\Event\PeremenayaDobavlenaVProekt;
use App\Unitman\Business\Model\Project\Event\PeremenayaUdalenaIzProekta;
use App\Unitman\Business\Model\Project\Event\ProektPostavlenVOcheredNaUdalenie;
use App\Unitman\Business\Model\Project\Event\ProektPostavlenVOcheredNaSborku;
use App\Unitman\Business\Model\Project\Event\ProjectDataWasChanged;
use App\Unitman\Business\Model\Project\Event\ProjectWasAdded;
use App\Unitman\Business\Model\Project\Event\ProjectWasBuilt;
use App\Unitman\Business\Model\Project\Event\ProjectWasDeleted;
use App\Unitman\Business\Model\Project\Event\ProjectWasDeletedManually;
use App\Unitman\Business\Model\Project\Event\ProjectWasDisabled;
use App\Unitman\Business\Model\Project\Event\ProjectWasEnabled;
use App\Unitman\Business\Model\Project\Event\ProjectWasNotBuilt;
use App\Unitman\Business\Model\Project\Event\ProjectWasNotDeleted;
use App\Unitman\Business\Model\Project\Event\UserAddedToProject;
use App\Unitman\Business\Model\Project\Event\UserRemovedFromProject;
use App\Unitman\Business\Model\Project\Event\UspehOchistkiProektaUstanovlen;
use App\Unitman\Business\Model\Project\Event\ZnacheniePeremnoiProektaIzmeneno;
use App\Unitman\Business\Model\Project\NastroikiHuka;
use App\Unitman\Business\Model\Project\ProjectCode;
use App\Unitman\Business\Model\Project\ProjectContainerSettings;
use App\Unitman\Business\Model\Project\ProjectDataAboutBuilding;
use App\Unitman\Business\Model\Project\ProjectDataAboutRemoving;
use App\Unitman\Business\Model\Project\ProjectId;
use App\Unitman\Business\Model\Project\ProjectName;
use App\Unitman\Business\Model\Project\ProjectUser;
use App\Unitman\Business\Model\Project\ProjectUserRole;
use App\Unitman\Business\Model\Project\ProjectVariable;
use App\Unitman\Business\Model\Project\ProjectVariableType;
use App\Unitman\Business\Model\Project\ProxyHost;
use App\Unitman\Business\Model\Runner\JobId;
use App\Unitman\Business\Model\Unit\Runner\RunnerJob;
use App\Unitman\Business\Model\Unit\Runner\RunnerJobType;
use DomainException;
use EventSauce\EventSourcing\AggregateRoot;
use EventSauce\EventSourcing\AggregateRootBehaviour;

/**
 * @template-implements AggregateRoot<ProjectId>
 * */
final class Project implements AggregateRoot
{
    /**
     * @template-use AggregateRootBehaviour<ProjectId>
     * */
    use AggregateRootBehaviour;

    /** @psalm-suppress PropertyNotSetInConstructor*/
    private string $repoId;
    /** @psalm-suppress PropertyNotSetInConstructor*/
    private ProjectCode $code;
    /** @psalm-suppress PropertyNotSetInConstructor*/
    private ProjectName $name;
    private bool $isActive = false;
    /**
     * @var ProjectUser[]
     * */
    private array $users = [];
    /** @psalm-suppress PropertyNotSetInConstructor*/
    private string $mainBranch;
    /** @psalm-suppress PropertyNotSetInConstructor*/
    private ProxyHost $proxyHost;
    /** @psalm-suppress PropertyNotSetInConstructor*/
    private NastroikiHuka $nastroikiHuka;
    /** @psalm-suppress PropertyNotSetInConstructor*/
    private ProjectContainerSettings $projectContainerSettings;

    private ?ProjectDataAboutBuilding $dataAboutBuilding = null;
    private ?ProjectDataAboutRemoving $dataAboutRemoving = null;

    private ?RunnerJob $ochistka = null;
    /**
     * @var ProjectVariable[]
     * */
    private array $variables = [];

    public function getId(): string
    {
        return $this->aggregateRootId->toString();
    }

    public function getRepoId(): string
    {
        return $this->repoId;
    }

    public static function addProject(string $id, AddProject $command, string $userId): self
    {
        $projectId = ProjectId::fromString($id);
        $project = new self($projectId);
        ProjectName::validate($command->projectName);
        ProjectContainerSettings::validateMemory($command->memoryLimit);
        $project->recordThat(new ProjectWasAdded($id, $command->repoId, $command->projectCode, $command->projectName, $command->mainBranch, $command->proxyHost, memoryLimit: $command->memoryLimit));
        $project->recordThat(new UserAddedToProject($id, $userId, ProjectUserRole::ADMIN->name));
        return $project;
    }

    private function applyProjectWasAdded(ProjectWasAdded $fact): void
    {
        $this->code = new ProjectCode($fact->projectCode);
        $this->name = new ProjectName($fact->projectName);
        $this->proxyHost = new ProxyHost($fact->proxyHost);
        $this->projectContainerSettings = new ProjectContainerSettings($fact->memoryLimit);

        $this->repoId = $fact->repoId;
        $this->mainBranch = $fact->mainBranch;
        $this->nastroikiHuka = new NastroikiHuka($fact->avtosozdanie, $fact->avtoobnovlenie, $fact->avtoudalenie, $fact->obnovlenieBezSbrosaPodgotovki);
    }

    public function proverkaProektaDlyNachlaOchistki(): void
    {
        if ($this->dataAboutRemoving) {
            throw new DomainException('project.removing');
        }

        if (!empty($this->ochistka) && !$this->ochistka->isFinish()) {
            throw new DomainException('project.ochistka_uge_nachalas');
        }

        if ($this->dataAboutBuilding && !$this->dataAboutBuilding->isFinish) {
            throw new DomainException('project.eshe_ne_sobran');
        }
    }

    public function nachatOchistku(JobId $jobId): void
    {
        $this->proverkaProektaDlyNachlaOchistki();
        $this->recordThat(new OchistkaProektaNachalas($this->getId(), (string) $jobId));
    }

    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyOchistkaProektaNachalas(OchistkaProektaNachalas $fact): void
    {
        $runnerJob = RunnerJob::make($fact->jobId, RunnerJobType::SBORKA);
        $runnerJob->start();
        $this->ochistka = $runnerJob;
    }

    public function ustanovitOshibkuOchistku(array $steps): void
    {
        if (empty($this->ochistka)) {
            throw new DomainException('project.ochistka_ne_nachalas');
        }

        if ($this->ochistka->isFinish()) {
            throw new DomainException('project.resultat_ochistki_uge_ustanovlen');
        }

        $this->recordThat(new OshibkaOchistkiProektaUstanovlena($this->getId(), $steps));
    }
    /**
     * @psalm-suppress PossiblyNullReference
     */
    public function applyOshibkaOchistkiProektaUstanovlena(OshibkaOchistkiProektaUstanovlena $fact): void
    {
        $this->ochistka->ustanovitResultat(false,$fact->steps);
    }

    public function ustanovitUspehOchistku(array $steps): void
    {
        if (empty($this->ochistka)) {
            throw new DomainException('project.ochistka_ne_nachalas');
        }

        if ($this->ochistka->isFinish()) {
            throw new DomainException('project.resultat_ochistki_uge_ustanovlen');
        }

        $this->recordThat(new UspehOchistkiProektaUstanovlen($this->getId(), $steps));
    }
    /**
     * @psalm-suppress PossiblyNullReference
     */
    public function applyUspehOchistkiProektaUstanovlen(UspehOchistkiProektaUstanovlen $fact): void
    {
        $this->ochistka->ustanovitResultat(true, $fact->steps);
    }

    public function obnovitNastrokiHuka(ObnovitNastroikiHuka $command): void
    {
        $this->recordThat(new NastroikiHukaProektaUstanovleni(
            $this->getId(),
            $command->avtosozdanie,
            $command->avtoobnovlenie,
            $command->avtoudalenie,
            $command->obnovlenieBezSbrosaPodgotovki
        ));
    }

    private function applyNastroikiHukaProektaUstanovleni(NastroikiHukaProektaUstanovleni $fact): void
    {
        $this->nastroikiHuka = new NastroikiHuka($fact->avtosozdanie, $fact->avtoobnovlenie, $fact->avtoudalenie, $fact->obnovlenieBezSbrosaPodgotovki);
    }

    private function findIndexUserById(string $userId): ?int
    {
        $index = null;

        foreach ($this->users as $i => $projectUser) {
            if ($projectUser->userId === $userId) {
                $index = $i;
            }
        }

        return $index;
    }

    public function addUser(Account $account): void
    {
        if ($this->dataAboutRemoving) {
            throw new DomainException('project.removing');
        }

        if ($account->isSystemRole) {
            throw new DomainException('project.user_has_system_role');
        }

        $userIndex = $this->findIndexUserById($account->id);

        if (isset($userIndex)) {
            throw new DomainException('project.user_already_exist');
        }

        $this->recordThat(new UserAddedToProject($this->getId(), $account->id, ProjectUserRole::USER->name));
    }

    private function applyUserAddedToProject(UserAddedToProject $fact): void
    {
        $this->users[] = new ProjectUser($fact->userId, ProjectUserRole::from($fact->role));
    }

    public function removeUser(RemoveUserFromProject $command): void
    {
        if ($this->dataAboutRemoving) {
            throw new DomainException('project.removing');
        }

        $userIndex = $this->findIndexUserById($command->userId);

        if (!isset($userIndex)) {
            throw new DomainException('project.user_not_found_in_project');
        }

        $this->recordThat(new UserRemovedFromProject($this->getId(), $command->userId));
    }

    private function applyUserRemovedFromProject(UserRemovedFromProject $fact): void
    {
        $userIndex = $this->findIndexUserById($fact->userId);
        if (isset($userIndex) && isset($this->users[$userIndex])) {
            unset($this->users[$userIndex]);
        }
    }

    public function changeData(UpdateProjectData $command): void
    {
        if ($this->dataAboutRemoving) {
            throw new DomainException('project.removing');
        }

        /*if ($this->dataAboutBuilding) {
            throw new \DomainException('project.already_built');
        }*/

        /*if ($command->newProjectName === (string)$this->name) {
            throw new \DomainException('project.old_name_equal_new_name');
        }*/
        ProjectName::validate($command->newProjectName);
        ProjectContainerSettings::validateMemory($command->memoryLimit);
        $this->recordThat(new ProjectDataWasChanged($this->getId(), $command->newProjectName, $command->newProxyHost, $command->memoryLimit));
    }

    private function applyProjectDataWasChanged(ProjectDataWasChanged $fact): void
    {
        $this->name = new ProjectName($fact->newName);
        $this->proxyHost = new ProxyHost($fact->newProxyHost);
        $this->projectContainerSettings = new ProjectContainerSettings($fact->memoryLimit);
    }

    public function postavitVOcheredNaUdanlenie(string $jobId): void
    {
        if ($this->dataAboutRemoving) {
            throw new DomainException('project.already_in_queue');
        }

        if ($this->dataAboutBuilding && !$this->dataAboutBuilding->isFinish) {
            throw new DomainException('project.in_pending_for_build');
        }

        $this->recordThat(new ProektPostavlenVOcheredNaUdalenie($this->getId(), $jobId));
    }
    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyProektPostavlenVOcheredNaUdalenie(ProektPostavlenVOcheredNaUdalenie $fact): void
    {
        $this->dataAboutRemoving = new ProjectDataAboutRemoving($fact->jobId);
    }

    public function successfullyRemoving(array $steps): void
    {
        if (!$this->dataAboutRemoving) {
            throw new DomainException('project.not_found_data_about_removing');
        }

        if ($this->dataAboutRemoving->isFinish) {
            throw new DomainException('project.removing_already_execute');
        }

        $this->recordThat(new ProjectWasDeleted($this->getId(), $steps));
    }
    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyProjectWasDeleted(ProjectWasDeleted $fact): void
    {
        $this->dataAboutRemoving = $this->dataAboutRemoving->success($fact->steps);
    }

    public function errorWhenRemoving(array $steps): void
    {
        if (!$this->dataAboutRemoving) {
            throw new DomainException('project.not_found_data_about_removing');
        }

        if ($this->dataAboutRemoving->isFinish) {
            throw new DomainException('project.removing_already_execute');
        }

        $this->recordThat(new ProjectWasNotDeleted($this->getId(), $steps, false));
    }
    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyProjectWasNotDeleted(ProjectWasNotDeleted $fact): void
    {
        $this->dataAboutRemoving = $this->dataAboutRemoving->fail($fact->steps);
        $this->isActive = $fact->isActive;
    }

    public function removeManually(): void
    {
        if (!$this->dataAboutRemoving) {
            throw new DomainException('project.not_found_data_about_removing');
        }

        if ($this->dataAboutRemoving->isFinish && $this->dataAboutRemoving->manually) {
            throw new DomainException('project.project_already_remove');
        }

        if ($this->dataAboutRemoving->isFinish && $this->dataAboutRemoving->success) {
            throw new DomainException('project.project_already_remove');
        }

        $this->recordThat(new ProjectWasDeletedManually($this->getId()));
    }
    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyProjectWasDeletedManually(ProjectWasDeletedManually $fact): void
    {
        $this->dataAboutRemoving = $this->dataAboutRemoving->removeManually();
    }

    public function postavitVOcheredNaSborku(string $jobId): void
    {
        $errors = $this->proverkaProektaDlyNachlaSborki();

        if (!empty($errors)) {
            throw new DomainException($errors[0]);
        }

        $this->recordThat(new ProektPostavlenVOcheredNaSborku($this->getId(), $jobId));
    }
    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyProektPostavlenVOcheredNaSborku(ProektPostavlenVOcheredNaSborku $fact): void
    {
        $this->dataAboutBuilding = new ProjectDataAboutBuilding($fact->jobId);
    }

    public function successfullyBuild(array $steps): void
    {
        if (!$this->dataAboutBuilding) {
            throw new DomainException('project.not_found_data_about_building');
        }

        if ($this->dataAboutBuilding->isFinish) {
            throw new DomainException('project.building_already_execute');
        }

        $this->recordThat(new ProjectWasBuilt($this->getId(), $steps));
    }
    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyProjectWasBuilt(ProjectWasBuilt $fact): void
    {
        $this->dataAboutBuilding = $this->dataAboutBuilding->success($fact->steps);
    }

    public function errorWhenBuild(array $steps): void
    {
        if (!$this->dataAboutBuilding) {
            throw new DomainException('project.not_found_data_about_building');
        }

        if ($this->dataAboutBuilding->isFinish) {
            throw new DomainException('project.building_already_execute');
        }

        $this->recordThat(new ProjectWasNotBuilt($this->getId(), $steps));
    }

    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyProjectWasNotBuilt(ProjectWasNotBuilt $fact): void
    {
        $this->dataAboutBuilding = $this->dataAboutBuilding->fail($fact->steps);
    }

    public function enable(): void
    {
        if (!$this->dataAboutBuilding) {
            throw new DomainException('project.not_found_data_about_building');
        }

        if (!$this->dataAboutBuilding->isFinish) {
            throw new DomainException('project.pending_building');
        }

        if ($this->isActive) {
            throw new DomainException('project.already_enabled');
        }

        if ($this->dataAboutRemoving) {
            throw new DomainException('project.removing');
        }

        $this->recordThat(new ProjectWasEnabled($this->getId()));
    }

    private function applyProjectWasEnabled(ProjectWasEnabled $fact): void
    {
        $this->isActive = true;
    }

    public function disable(): void
    {
        if (!$this->dataAboutBuilding) {
            throw new DomainException('project.not_found_data_about_building');
        }

        if (!$this->dataAboutBuilding->isFinish) {
            throw new DomainException('project.pending_building');
        }

        if ($this->dataAboutRemoving) {
            throw new DomainException('project.removing');
        }

        if (!$this->isActive) {
            throw new DomainException('project.already_disabled');
        }

        $this->recordThat(new ProjectWasDisabled($this->getId()));
    }

    private function applyProjectWasDisabled(ProjectWasDisabled $fact): void
    {
        $this->isActive = false;
    }

    public function getCode(): string
    {
        return (string) $this->code;
    }

    public function getName(): string
    {
        return (string) $this->name;
    }

    public function isDisable(): bool
    {
        return !$this->isActive;
    }

    public function esliRazreshenoSobiratUniti(string $userId): bool
    {
        $index = $this->findIndexUserById($userId);

        return isset($index);
    }

    public function getMainBranchName(): string
    {
        return $this->mainBranch;
    }

    public function esliProektBilSobran(): bool
    {
        return isset($this->dataAboutBuilding) && $this->dataAboutBuilding->isFinish;
    }

    /**
     * @return array|string
     */
    public function proverkaProektaDlyNachlaSborki(): string|array
    {
        $errors = [];
        if ($this->dataAboutBuilding) {
            $errors = 'project.already_in_queue';
        }

        if ($this->dataAboutRemoving) {
            $errors = 'project.removed';
        }
        return $errors;
    }

    public function getProxyHost(): ProxyHost
    {
        return $this->proxyHost;
    }

    public function getProjectUserById(string $userId): ProjectUser
    {
        $result = null;

        foreach ($this->users as $user) {
            if ($user->userId === $userId) {
                $result = $user;
            }
        }

        if (empty($result)) {
            throw new \DomainException('project.user_not_found');
        }

        return $result;
    }

    public function dobavitPeremenuyu(DobavitPeremenuyuVProekt $command): void
    {
        foreach ($this->variables as $variable) {
            if ($variable->code == $command->code) {
                throw new \DomainException('project.variable.duplicate');
            }
        }

        ProjectVariable::validate($command->code, $command->value);

        $this->recordThat(new PeremenayaDobavlenaVProekt($this->getId(), $command->tip, $command->code, $command->value));
    }

    private function applyPeremenayaDobavlenaVProekt(PeremenayaDobavlenaVProekt $fact): void
    {
        $this->variables[] = new ProjectVariable(ProjectVariableType::from($fact->tip), $fact->code, $fact->value);
    }


    /**
     * @param string $code
     * @return int|null
     */
    public function findVariableIndexByCode(string $code): ?int
    {
        $index = null;

        foreach ($this->variables as $i => $variable) {
            if ($variable->code == $code) {
                $index = $i;
            }
        }
        return $index;
    }

    public function udalitPeremenuyu(UdalitPeremenuyuIzProekta $command): void
    {
        $index = $this->findVariableIndexByCode($command->code);

        if (!isset($index)) {
            throw new \DomainException('project.variable.not_found');
        }

        $this->recordThat(new PeremenayaUdalenaIzProekta($this->getId(), $command->code));
    }

    public function applyPeremenayaUdalenaIzProekta(PeremenayaUdalenaIzProekta $fact): void
    {
        $index = $this->findVariableIndexByCode($fact->code);
        if (isset($index)) {
            unset($this->variables[$index]);
        }

    }

    public function izmenitZnacheniePeremenoi(IzmenitZnacheniePeremenoiProekta $command): void
    {
        $index = $this->findVariableIndexByCode($command->code);

        if (!isset($index)) {
            throw new \DomainException('project.variable.not_found');
        }

        if ($this->variables[$index]->value === $command->newValue) {
            throw new \DomainException('project.variable.new_value_equal_old_value');
        }

        $this->recordThat(new ZnacheniePeremnoiProektaIzmeneno($this->getId(), $command->code, $command->newValue));
    }

    public function applyZnacheniePeremnoiProektaIzmeneno(ZnacheniePeremnoiProektaIzmeneno $fact): void
    {
        $index = $this->findVariableIndexByCode($fact->code);
        if (!isset($index)) {
            throw new \DomainException('project.variable.not_found');
        }
        $oldVariable = $this->variables[$index];
        $this->variables[$index] = new ProjectVariable($oldVariable->tip, $oldVariable->code, $fact->newValue);
    }

    public function poluchitPeremenieProekta(): array
    {
        return $this->variables;
    }

    public function poluchitNastroikiHuka(): NastroikiHuka
    {
        return $this->nastroikiHuka;
    }

    public function poluchitWorkflowIdDlySborki(): string
    {
        if (empty($this->dataAboutBuilding)) {
            throw new DomainException('unit.prozes_sborki_ne_naiden');
        }
        return $this->dataAboutBuilding->jobId;
    }

    public function poluchitWorkflowIdDlyUdaleniya(): string
    {
        if (empty($this->dataAboutRemoving)) {
            throw new DomainException('unit.prozes_udaleniya_ne_naiden');
        }
        return $this->dataAboutRemoving->jobId;
    }

    public function poluchitWorkflowIdDlyOchistki(): string
    {
        if (empty($this->ochistka)) {
            throw new DomainException('unit.prozes_ochistki_ne_naiden');
        }
        return $this->ochistka->getJobId();
    }
}
