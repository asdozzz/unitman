<?php

namespace App\Unitman\Business\Model;

use App\Unitman\Business\Command\Unit\SozdatUnit;
use App\Unitman\Business\Command\Unit\VipolnitDeistviye;
use App\Unitman\Business\Model\Project\ProjectUser;
use App\Unitman\Business\Model\Project\ProjectUserRole;
use App\Unitman\Business\Model\Runner\JobId;
use App\Unitman\Business\Model\Unit\ConfigUnita;
use App\Unitman\Business\Model\Unit\ConfigUnita\KonfigServisa;
use App\Unitman\Business\Model\Unit\Event\DeistviePrikreplenoKJobe;
use App\Unitman\Business\Model\Unit\Event\DobavlenProzesVUnit;
use App\Unitman\Business\Model\Unit\Event\KodVetkiIzmenilsyaVHranilishe;
use App\Unitman\Business\Model\Unit\Event\KonfigUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\ObnovlenieUnitaNachalos;
use App\Unitman\Business\Model\Unit\Event\OshibkaDeistviyaUstanovlena;
use App\Unitman\Business\Model\Unit\Event\OshibkaObnovleniyaUnitaUstanovlena;
use App\Unitman\Business\Model\Unit\Event\OshibkaOstanovkiUnitaUstanovlena;
use App\Unitman\Business\Model\Unit\Event\OshibkaPodgotovkiUnitaUstanovlena;
use App\Unitman\Business\Model\Unit\Event\OshibkaSborkiUnitaUstanovlena;
use App\Unitman\Business\Model\Unit\Event\OshibkaSbrosaPodgotovkiUnitaUstanovlena;
use App\Unitman\Business\Model\Unit\Event\OshibkaUdaleniyaUnitaUstanovlena;
use App\Unitman\Business\Model\Unit\Event\OshibkaZapuskaUnitaUstanovlena;
use App\Unitman\Business\Model\Unit\Event\OstanovkaUnitaNachalas;
use App\Unitman\Business\Model\Unit\Event\PeremenieUnitaZapolneni;
use App\Unitman\Business\Model\Unit\Event\PodgotovkaUnitaNachalas;
use App\Unitman\Business\Model\Unit\Event\SborkaUnitNachalas;
use App\Unitman\Business\Model\Unit\Event\SbrosPodgotovkiNachalsya;
use App\Unitman\Business\Model\Unit\Event\SlomaniyUnitUdalen;
use App\Unitman\Business\Model\Unit\Event\UdalenieUnitaNachalos;
use App\Unitman\Business\Model\Unit\Event\UnitSbroshenDoSostoyaniyaSborki;
use App\Unitman\Business\Model\Unit\Event\UnitSozdan;
use App\Unitman\Business\Model\Unit\Event\UnitSozdanSystemoi;
use App\Unitman\Business\Model\Unit\Event\UspehDeistviyaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\UspehObnovleniyaUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\UspehOstanovkiUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\UspehPodgotovkiUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\UspehSborkiUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\UspehSbrosaPodgotovkiUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\UspehUdaleniyaUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\UspehZapuskaUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\VipolnenieDeistviyaNachalos;
use App\Unitman\Business\Model\Unit\Event\ZadachiDobavleniVProzesUnita;
use App\Unitman\Business\Model\Unit\Event\ZapuskUnitNachalsya;
use App\Unitman\Business\Model\Unit\Runner\RunnerJob;
use App\Unitman\Business\Model\Unit\Runner\RunnerJobStep;
use App\Unitman\Business\Model\Unit\Runner\RunnerJobType;
use App\Unitman\Business\Model\Unit\State\AbstractState;
use App\Unitman\Business\Model\Unit\State\OshibkaObnovleniya;
use App\Unitman\Business\Model\Unit\State\OshibkaOstanovki;
use App\Unitman\Business\Model\Unit\State\OshibkaPodgotovki;
use App\Unitman\Business\Model\Unit\State\OshibkaSborki;
use App\Unitman\Business\Model\Unit\State\OshibkaSbrosaPodgotovki;
use App\Unitman\Business\Model\Unit\State\OshibkaZapuska;
use App\Unitman\Business\Model\Unit\State\Podgotovlen;
use App\Unitman\Business\Model\Unit\State\Sloman;
use App\Unitman\Business\Model\Unit\State\Sobran;
use App\Unitman\Business\Model\Unit\State\Sozdan;
use App\Unitman\Business\Model\Unit\State\StateFactory;
use App\Unitman\Business\Model\Unit\State\Udalen;
use App\Unitman\Business\Model\Unit\State\UdalenVruchnuyu;
use App\Unitman\Business\Model\Unit\State\VOcherediNaObnovlenie;
use App\Unitman\Business\Model\Unit\State\VOcherediNaOstanovku;
use App\Unitman\Business\Model\Unit\State\VOcherediNaPodgotovku;
use App\Unitman\Business\Model\Unit\State\VOcherediNaSborku;
use App\Unitman\Business\Model\Unit\State\VOcherediNaSbrosPodgotovki;
use App\Unitman\Business\Model\Unit\State\VOcherediNaUdalenie;
use App\Unitman\Business\Model\Unit\State\VOcherediNaVipolnenieDeistviya;
use App\Unitman\Business\Model\Unit\State\VOcherediNaZapusk;
use App\Unitman\Business\Model\Unit\State\Zapushen;
use App\Unitman\Business\Model\Unit\StatistikaKonteinera;
use App\Unitman\Business\Model\Unit\UnitBranch;
use App\Unitman\Business\Model\Unit\UnitId;
use App\Unitman\Business\Model\Unit\UnitName;
use App\Unitman\Business\Model\Unit\UnitProcess;
use App\Unitman\Business\Model\Unit\UnitProject;
use App\Unitman\Business\Model\Unit\VariableValue;
use DomainException;
use EventSauce\EventSourcing\AggregateRoot;
use EventSauce\EventSourcing\AggregateRootBehaviour;
use Exception;
use Ramsey\Uuid\Uuid;

/**
 * @template-implements AggregateRoot<UnitId>
 * */
final class Unit implements AggregateRoot
{
    /** @psalm-suppress PropertyNotSetInConstructor*/
    private ?UnitName $name;
    /** @psalm-suppress PropertyNotSetInConstructor*/
    private ?UnitBranch $branch;
    /** @psalm-suppress PropertyNotSetInConstructor*/
    private ?UnitProject $project;
    /** @psalm-suppress PropertyNotSetInConstructor*/
    private ?string $authorId;

    /** @psalm-suppress PropertyNotSetInConstructor*/
    private ?AbstractState $state;
    private ?ConfigUnita $configUnita = null;

    private ?string $url = null;

    /**
     * @var array<VariableValue>
     * */
    private array $variableValues = [];
    private ?RunnerJob $sborka = null;
    private ?RunnerJob $podgotovka = null;
    private ?RunnerJob $obnovlenie = null;
    private ?RunnerJob $sbrosPodgotovki = null;
    private ?RunnerJob $zapusk = null;
    private ?RunnerJob $ostanovka = null;
    private ?RunnerJob $udalenie = null;
    private ?RunnerJob $vipolnenieDeistviya = null;


    private bool $isDeleted = false;

    private ?UnitBranch $newBranch = null;

    private ?int $unixtimePoslednegoObnovleniyaKodaUnita = null;

    private ?int $unixtimePoslednegoObnovleniyaKodaVHranilishe = null;

    private bool $unitSozdanSystemoi = false;

    private ?StatistikaKonteinera $statistikaKonteinera = null;

    /**
     * @template-use AggregateRootBehaviour<UnitId>
     * */
    use AggregateRootBehaviour;

    /**
     * @var Unit\UnitProcess[]
     * */
    private array $prozesi = [];

    private array $mapActions = [];

    public function getId(): string
    {
        return $this->aggregateRootId->toString();
    }

    function esliZapushen(): bool
    {
        return !empty($this->zapusk) && $this->zapusk->isSuccess();
    }

    function esliSobran(): bool
    {
        return !empty($this->sborka) && $this->sborka->isSuccess();
    }

    function esliPodgotovlen(): bool
    {
        return !empty($this->podgotovka) && $this->podgotovka->isSuccess();
    }

    public function isWaitResultFromRunner(): bool
    {
        foreach ($this->prozesi as $process) {
            foreach ($process->jobs as $job) {
                if ($job->isStart()) {
                    return true;
                }
            }
        }

        return false;
    }

    public function esliRazreshenoUpravlyatUnitom(ProjectUser $projectUser): bool
    {
        return true;
    }

    public function proveritZnacheniePeremenihDeistviya(string $actionId, array $values): void
    {
        $config = $this->checkIssetConfig();

        $errs = $config->validateActionValues($actionId, $values);

        if (!empty($errs)) {
            throw new DomainException(join(',', $errs));
        }
    }


    private function dobavitProzes(UnitProcess $process): void
    {
        if ($this->isDeleted) {
            throw new DomainException('unit.udalen');
        }

        $this->recordThat(new DobavlenProzesVUnit($this->getId(), $process->toArray()));
    }

    private function applyDobavlenProzesVUnit(DobavlenProzesVUnit $fact): void
    {
        $this->prozesi[] = UnitProcess::fromArray($fact->prozes);
    }

    function ustanovitZadachiDlyProzesa(string $prozesId, array $zadachi): void
    {
        $finded = $this->poluchitProzesPoId($prozesId);

        $finded->dobavitZadachiVProzess($zadachi);
        $this->recordThat(new ZadachiDobavleniVProzesUnita($this->getId(), $finded->toArray()));
    }

    private function applyZadachiDobavleniVProzesUnita(ZadachiDobavleniVProzesUnita $fact): void
    {
        $prozes = UnitProcess::fromArray($fact->process);
        $this->obnovitProzes($prozes);
    }

    private function obnovitProzes(UnitProcess $updatedProcess): void
    {
        $newArray = [];
        foreach ($this->prozesi as $process) {
            if ($process->id === $updatedProcess->id) {
                $newArray[] = $updatedProcess;
            } else {
                $newArray[] = $process;
            }
        }

        $this->prozesi = $newArray;
    }

    private function poluchitProzesPoIdZadachi(string $jobId): UnitProcess
    {
        $result = null;
        foreach ($this->prozesi as $prozes) {
            if ($prozes->esliEstZadacha($jobId)) {
                $result = $prozes;
            }
        }

        if (empty($result)) {
            throw new DomainException('unit.prozess.not_found_by_job_id');
        }

        return $result;
    }

    private function startProzes(string $jobId): void
    {
        foreach ($this->prozesi as $prozes) {
            if ($prozes->esliEstZadacha($jobId)) {
                $prozes->startJob($jobId);
            }
        }
    }

    private function getJobById(string $jobId): RunnerJob
    {
        $job = null;

        foreach ($this->prozesi as $prozes) {
            if ($prozes->esliEstZadacha($jobId)) {
                $job = $prozes->findJob($jobId);
            }
        }

        if (empty($job)) {
            throw new \Exception('unit.job_not_found');
        }

        return $job;
    }

    private function ustanovitResultatZadachi(string $jobId, bool $success, array $steps): void
    {
        foreach ($this->prozesi as $prozes) {
            if ($prozes->esliEstZadacha($jobId)) {
                $prozes->ustanovitResultatZadachi($jobId, $success, array_map(fn(array $stepArr) => RunnerJobStep::fromArray($stepArr),$steps));
                break;
            }
        }
    }

    public function dobavitProzesVipolneniyaDeistviya(string $userId, string $prozesId, VipolnitDeistviye $command): void
    {
        if ($this->isDeleted) {
            throw new DomainException('unit.udalen');
        }

        $prozes = UnitProcess::make($prozesId,$userId, UnitProcess\UnitProcessType::DEISTVIE);
        $jobId = Uuid::uuid7()->toString();
        $prozes->dobavitZadachiVProzess([
            RunnerJob::make($jobId, RunnerJobType::DEISTVIE),
        ]);
        $this->dobavitProzes($prozes);
        $this->recordThat(new DeistviePrikreplenoKJobe($this->getId(), $jobId, $command->actionId, $command->values));
    }

    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyDeistviePrikreplenoKJobe(DeistviePrikreplenoKJobe $fact): void
    {
        $this->mapActions[$fact->jobId] = [
            'actionId' => $fact->actionId,
            'values' => $fact->values
        ];
    }

    public function vipolnitDeistvie(string $jobId,int $unixtime): void
    {
        if ($this->isDeleted) {
            throw new DomainException('unit.udalen');
        }

        if ($this->isWaitResultFromRunner()) {
            throw new DomainException('unit.wait_runner');
        }

        if (!$this->esliZapushen()) {
            throw new DomainException('unit.unit_ne_zapushen');
        }

        $state = $this->newState(new VOcherediNaVipolnenieDeistviya());
        $prozes = $this->poluchitProzesPoIdZadachi($jobId);
        $prozes->startJob($jobId);
        $this->recordThat(new VipolnenieDeistviyaNachalos($this->getId(), $jobId, $state->toArray($this), $unixtime, $prozes->toArray()));
    }

    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyVipolnenieDeistviyaNachalos(VipolnenieDeistviyaNachalos $fact): void
    {
        $prozess = UnitProcess::fromArray($fact->prozess);
        $this->obnovitProzes($prozess);
        $this->vipolnenieDeistviya = $this->getJobById($fact->jobId);
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    public function ustanovitUspehDeistviya(string $jobId, array $steps): void
    {
        if (empty($this->vipolnenieDeistviya)) {
            throw new DomainException('unit.vipolnenieDeistviya_ne_nachalas');
        }

        if ($this->vipolnenieDeistviya->isFinish()) {
            throw new DomainException('unit.resultat_deistviya_uge_ustanovlen');
        }

        $this->state = $this->newState(new Zapushen());
        $prozes = $this->poluchitProzesPoIdZadachi($jobId);
        $prozes->ustanovitResultatZadachi($jobId, true, $steps);
        $this->recordThat(new UspehDeistviyaUstanovlen($this->getId(), $jobId, $steps, $this->state->toArray($this), $prozes->toArray()));
    }

    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyUspehDeistviyaUstanovlen(UspehDeistviyaUstanovlen $fact): void
    {
        $prozess = UnitProcess::fromArray($fact->prozess);
        $this->obnovitProzes($prozess);
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
        $this->vipolnenieDeistviya = $this->getJobById($fact->jobId);
    }

    public function ustanovitOshibkuDeistviya(string $jobId, array $steps): void
    {
        if (empty($this->vipolnenieDeistviya)) {
            throw new DomainException('unit.vipolnenieDeistviya_ne_nachalas');
        }

        if ($this->vipolnenieDeistviya->isFinish()) {
            throw new DomainException('unit.resultat_deistviya_uge_ustanovlen');
        }

        $this->state = $this->newState(new Zapushen());
        $prozes = $this->poluchitProzesPoIdZadachi($jobId);
        $prozes->ustanovitResultatZadachi($jobId, false, $steps);
        $this->recordThat(new OshibkaDeistviyaUstanovlena($this->getId(), $jobId, $steps, $this->state->toArray($this), $prozes->toArray()));
    }

    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyOshibkaDeistviyaUstanovlena(OshibkaDeistviyaUstanovlena $fact): void
    {
        $prozess = UnitProcess::fromArray($fact->prozess);
        $this->obnovitProzes($prozess);
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
        $this->vipolnenieDeistviya = $this->getJobById($fact->jobId);
    }

    public static function sozdatUnit(string $id, string $authorId, string $prozesId,SozdatUnit $command): self
    {
        if (empty($command->projectId)) {
            throw new DomainException('unit.projectId_is_empty');
        }
        if (empty($authorId)) {
            throw new DomainException('unit.authorId_is_empty');
        }
        $unit = new self(UnitId::fromString($id));
        $state = new Sozdan();
        $peremenie = [];
        foreach ($command->znacheniePeremenoi as $peremenaya) {
            try {
                $variable = new VariableValue($peremenaya->id, $peremenaya->value, $peremenaya->type);
                $peremenie[] = $variable->toArray();
            } catch (\Exception) {
            }
        }
        $unit->recordThat(new UnitSozdan($id, $authorId, $command->projectId, $command->unitName, $command->branch, $state->toArray($unit), $peremenie));
        $unit->dobavitProzesSborki($authorId, $prozesId);
        return $unit;
    }

    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyUnitSozdan(UnitSozdan $fact): void
    {
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
        $this->project = new UnitProject($fact->projectId);
        $this->name = new UnitName($fact->name);
        $this->branch = new UnitBranch($fact->branch);
        $this->authorId = $fact->authorId;
        $this->variableValues = array_map(function (array $item) {
            return new VariableValue($item['id'], $item['value'], $item['type']);
        }, $fact->values);
    }

    public static function sozdatUnitSystemoi(string $id, Account $account, SozdatUnit $command): self
    {
        if (empty($command->projectId)) {
            throw new DomainException('unit.projectId_is_empty');
        }
        if (!$account->isSystemRole) {
            throw new DomainException('unit.author_must_have_system_role');
        }
        $unit = new self(UnitId::fromString($id));
        $state = new Sozdan();
        $unit->recordThat(new UnitSozdanSystemoi($id, $account->id, $command->projectId, $command->unitName, $command->branch, $state->toArray($unit)));
        return $unit;
    }

    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyUnitSozdanSystemoi(UnitSozdanSystemoi $fact): void
    {
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
        $this->project = new UnitProject($fact->projectId);
        $this->name = new UnitName($fact->name);
        $this->branch = new UnitBranch($fact->branch);
        $this->authorId = $fact->authorId;
        $this->unitSozdanSystemoi = true;
    }

    /**
     * @param array $values
     * @return array
     */
    private function makeVariableCollectionByArray(array $values): array
    {
        $config = $this->checkIssetConfig();
        $tmpVariables = [];
        $typeMap = $config->getVariableTypeMap();
        foreach ($values as $id => $value) {
            if (empty($typeMap[$id])) {
                throw new DomainException('unit.config.type_for_variable_not_found');
            }
            $tmpVariables[] = new VariableValue($id, $value, $typeMap[$id]);
        }
        return $tmpVariables;
    }

    private function newState(AbstractState $state): AbstractState
    {
        if (empty($this->state)) {
            throw new DomainException('unit.state_not_found');
        }
        return $this->state->newState($state);
    }

    public function dobavitProzesSborki(string $userId, string $prozesId): void
    {
        if ($this->isDeleted) {
            throw new DomainException('unit.udalen');
        }

        if (!empty($this->sborka) && $this->sborka->isSuccess()) {
            throw new DomainException('unit.uge_sobran');
        }

        $prozes = UnitProcess::make($prozesId,$userId, UnitProcess\UnitProcessType::SBORKA);
        $prozes->dobavitZadachiVProzess([
            RunnerJob::make( Uuid::uuid7()->toString(), RunnerJobType::SBORKA),
            RunnerJob::make(Uuid::uuid7()->toString(), RunnerJobType::PODGOTOVKA),
            RunnerJob::make(Uuid::uuid7()->toString(), RunnerJobType::ZAPUSK),
        ]);
        $this->dobavitProzes($prozes);
    }

    public function nachatSborkuUnita(string $jobId, int $unixtime): void
    {
        $state = $this->newState(new VOcherediNaSborku());
        $prozes = $this->poluchitProzesPoIdZadachi($jobId);
        $prozes->startJob($jobId);
        $this->recordThat(new SborkaUnitNachalas($this->getId(), $jobId, $state->toArray($this), $unixtime, $prozes->toArray()));
    }

    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applySborkaUnitNachalas(SborkaUnitNachalas $fact): void
    {
        $prozess = UnitProcess::fromArray($fact->prozess);
        $this->obnovitProzes($prozess);
        $this->sborka = $this->getJobById($fact->jobId);
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
        $this->unixtimePoslednegoObnovleniyaKodaUnita = $fact->unixtime;
        $this->unixtimePoslednegoObnovleniyaKodaVHranilishe = $fact->unixtime;
    }

    public function ustanovitOshibkuSborki(string $jobId, array $steps): void
    {
        $prozes = $this->poluchitProzesPoIdZadachi($jobId);
        $prozes->ustanovitResultatZadachi($jobId, false, $steps);
        $state = $this->newState(new OshibkaSborki());
        $this->recordThat(new OshibkaSborkiUnitaUstanovlena($this->getId(), $jobId, $state->toArray($this), $prozes->toArray()));
    }

    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyOshibkaSborkiUnitaUstanovlena(OshibkaSborkiUnitaUstanovlena $fact): void
    {
        $prozess = UnitProcess::fromArray($fact->prozess);
        $this->obnovitProzes($prozess);
        $this->sborka = $this->getJobById($fact->jobId);
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    public function ustanovitUspehSborki(string $jobId,array $steps, array $configUnita): void
    {
        //TODO вынести создание объекта конфига наружу
        $prozes = $this->poluchitProzesPoIdZadachi($jobId);
        $prozes->ustanovitResultatZadachi($jobId, true, $steps);

        $state = $this->newState(new Sobran());
        $this->recordThat(new UspehSborkiUnitaUstanovlen($this->getId(), $jobId, $state->toArray($this), $prozes->toArray()));

        $errs = $this->validateConfig($configUnita);

        if (empty($errs)) {
            $this->recordThat(new KonfigUnitaUstanovlen($this->getId(), $configUnita));
        }

    }

    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyUspehSborkiUnitaUstanovlen(UspehSborkiUnitaUstanovlen $fact): void
    {
        $prozess = UnitProcess::fromArray($fact->prozess);
        $this->obnovitProzes($prozess);
        $this->sborka = $this->getJobById($fact->jobId);
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    public function applyKonfigUnitaUstanovlen(KonfigUnitaUstanovlen $fact): void
    {
        $this->configUnita = ConfigUnita::fromArray($fact->configUnita);
    }

    public function validateConfig(array $configUnita): array
    {
        $errors = [];
        try {
            if (empty($configUnita)) {
                throw new DomainException('unit.config_is_empty');
            }
            ConfigUnita::fromArray($configUnita);
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }

        return $errors;
    }

    public function zapolnitPeremenie(array $values): void
    {
        if ($this->isDeleted) {
            throw new DomainException('unit.udalen');
        }

        if ($this->isWaitResultFromRunner()) {
            throw new DomainException('unit.wait_runner');
        }

        $config = $this->checkIssetConfig();

        $tmpVariables = $this->makeVariableCollectionByArray($values);

        $errs = $config->validateValues($tmpVariables);
        if (!empty($errs)) {
            throw new DomainException(join(', ', $errs));
        }

        $this->variableValues = $tmpVariables;

        if (empty($this->state)) {
            $state = new Sozdan();
        } else {
            $state = $this->state;
        }

        $this->recordThat(new PeremenieUnitaZapolneni($this->getId(), $values, $state->toArray($this)));
    }

    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyPeremenieUnitaZapolneni(PeremenieUnitaZapolneni $fact): void
    {
        $this->variableValues = $this->makeVariableCollectionByArray($fact->values);
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    public function esliConfigZapolnenPravilon(): bool
    {
        if (empty($this->configUnita)) {
            return false;
        }

        $errs = $this->configUnita->validateValues($this->variableValues);

        if (!empty($errs)) {
            return false;
        }

        return true;
    }

    public function dobavitProzesPodgotovki(string $userId, string $prozesId): void
    {
        $this->validaziyaPeredPodgotovkoi();
        $prozes = UnitProcess::make($prozesId, $userId,UnitProcess\UnitProcessType::PODGOTOVKA);
        $prozes->dobavitZadachiVProzess([
            RunnerJob::make( Uuid::uuid7()->toString(), RunnerJobType::PODGOTOVKA),
        ]);
        $this->dobavitProzes($prozes);
    }

    public function nachatPodgotovkuUnita(string $jobId): void
    {
        $this->validaziyaPeredPodgotovkoi();

        $state = $this->newState(new VOcherediNaPodgotovku());
        $prozes = $this->poluchitProzesPoIdZadachi($jobId);
        /*echo "AAAAAAAA:".$jobId;
        die("<pre>" . print_r($prozes->jobs, true) . "</pre>");*/
        $prozes->startJob($jobId);
        $this->recordThat(new PodgotovkaUnitaNachalas($this->getId(), $jobId, $state->toArray($this), $prozes->toArray()));
    }

    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyPodgotovkaUnitaNachalas(PodgotovkaUnitaNachalas $fact): void
    {
        $prozess = UnitProcess::fromArray($fact->prozess);
        $this->obnovitProzes($prozess);
        $this->podgotovka = $this->getJobById($fact->jobId);
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    public function ustanovitOshibkuPodgotovki(string $jobId, array $steps): void
    {
        $prozes = $this->poluchitProzesPoIdZadachi($jobId);
        $prozes->ustanovitResultatZadachi($jobId, false, $steps);
        $state = $this->newState(new OshibkaPodgotovki());
        $this->recordThat(new OshibkaPodgotovkiUnitaUstanovlena($this->getId(), $jobId, $state->toArray($this), $prozes->toArray()));
    }

    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyOshibkaPodgotovkiUnitaUstanovlena(OshibkaPodgotovkiUnitaUstanovlena $fact): void
    {
        $prozess = UnitProcess::fromArray($fact->prozess);
        $this->obnovitProzes($prozess);
        $this->podgotovka = $this->getJobById($fact->jobId);
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    public function ustanovitUspehPodgotovki(string $jobId, array $steps): void
    {
        $prozes = $this->poluchitProzesPoIdZadachi($jobId);
        $prozes->ustanovitResultatZadachi($jobId, true, $steps);
        $state = $this->newState(new Podgotovlen());
        $this->recordThat(new UspehPodgotovkiUnitaUstanovlen($this->getId(), $jobId, $state->toArray($this), $prozes->toArray()));
    }

    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyUspehPodgotovkiUnitaUstanovlen(UspehPodgotovkiUnitaUstanovlen $fact): void
    {
        $prozess = UnitProcess::fromArray($fact->prozess);
        $this->obnovitProzes($prozess);
        $this->podgotovka = $this->getJobById($fact->jobId);
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    //---------Обновление
    public function dobavitProzesObnovleniya(string $userId, string $prozesId): void
    {
        $this->proverkaVozmognostiObnovleniyaUnita();
        $prozes = UnitProcess::make($prozesId,$userId, UnitProcess\UnitProcessType::OBNOVLENIE);
        $zadachi = [];
        if ($this->sborka && $this->sborka->isFinish()) {
            $zadachi[] = RunnerJob::make( Uuid::uuid7()->toString(), RunnerJobType::OSTANOVKA);
        }
        if ($this->podgotovka && $this->podgotovka->isFinish()) {
            $zadachi[] = RunnerJob::make(Uuid::uuid7()->toString(), RunnerJobType::SBROS_PODGOTOVKI);
        }
        $zadachi[] = RunnerJob::make(Uuid::uuid7()->toString(), RunnerJobType::OBNOVLENIE);
        $zadachi[] = RunnerJob::make(Uuid::uuid7()->toString(), RunnerJobType::PODGOTOVKA);
        $zadachi[] = RunnerJob::make(Uuid::uuid7()->toString(), RunnerJobType::ZAPUSK);

        $prozes->dobavitZadachiVProzess($zadachi);
        $this->dobavitProzes($prozes);
    }

    public function nachatObnovlenieUnita(string $jobId, int $unixtime): void
    {
        $this->proverkaVozmognostiObnovleniyaUnita();
        $prozes = $this->poluchitProzesPoIdZadachi($jobId);
        $prozes->startJob($jobId);
        $state = $this->newState(new VOcherediNaObnovlenie());
        $this->recordThat(new ObnovlenieUnitaNachalos($this->getId(), $jobId, $state->toArray($this), $unixtime, $prozes->toArray()));
    }

    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyObnovlenieUnitaNachalos(ObnovlenieUnitaNachalos $fact): void
    {
        $prozess = UnitProcess::fromArray($fact->prozess);
        $this->obnovitProzes($prozess);
        $this->obnovlenie = $this->getJobById($fact->jobId);
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
        $this->unixtimePoslednegoObnovleniyaKodaUnita = $fact->unixtime;
        $this->unixtimePoslednegoObnovleniyaKodaVHranilishe = $fact->unixtime;
    }

    public function ustanovitOshibkuObnovleniya(string $jobId, array $steps): void
    {
        $prozes = $this->poluchitProzesPoIdZadachi($jobId);
        $prozes->ustanovitResultatZadachi($jobId, false, $steps);
        $state = $this->newState(new OshibkaObnovleniya());
        $this->recordThat(new OshibkaObnovleniyaUnitaUstanovlena($this->getId(), $jobId, $state->toArray($this), $prozes->toArray()));
    }
    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyOshibkaObnovleniyaUnitaUstanovlena(OshibkaObnovleniyaUnitaUstanovlena $fact): void
    {
        $prozess = UnitProcess::fromArray($fact->prozess);
        $this->obnovitProzes($prozess);
        $this->obnovlenie = $this->getJobById($fact->jobId);
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    public function ustanovitUspehObnovleniya(string $jobId, array $steps, array $configUnita): void
    {
        $prozes = $this->poluchitProzesPoIdZadachi($jobId);
        $prozes->ustanovitResultatZadachi($jobId, true, $steps);

        if ($this->esliZapushen()) {
            $state = $this->newState(new Zapushen());
        } elseif ($this->esliPodgotovlen()) {
            $state = $this->newState(new Podgotovlen());
        } else {
            $state = $this->newState(new Sobran());
        }

        $this->recordThat(new UspehObnovleniyaUnitaUstanovlen($this->getId(), $jobId, $state->toArray($this), $prozes->toArray()));

        $errs = $this->validateConfig($configUnita);

        if (empty($errs)) {
            $this->recordThat(new KonfigUnitaUstanovlen($this->getId(), $configUnita));
        }

    }
    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyUspehObnovleniyaUnitaUstanovlen(UspehObnovleniyaUnitaUstanovlen $fact): void
    {
        $prozess = UnitProcess::fromArray($fact->prozess);
        $this->obnovitProzes($prozess);
        $this->obnovlenie = $this->getJobById($fact->jobId);

        if (!empty($fact->configUnita)) {
            $this->configUnita = ConfigUnita::fromArray($fact->configUnita);
        }

        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    //----------Сброс подготовки
    public function dobavitProzesSbrosaPodgotovki(string $userId, string $prozesId): void
    {
        $this->validaziyaPeredSbrosomPodgotovkoi();
        $prozes = UnitProcess::make($prozesId,$userId, UnitProcess\UnitProcessType::SBROS_PODGOTOVKI);
        $zadachi = [];
        $zadachi[] = RunnerJob::make( Uuid::uuid7()->toString(), RunnerJobType::SBROS_PODGOTOVKI);

        $prozes->dobavitZadachiVProzess($zadachi);
        $this->dobavitProzes($prozes);
    }
    public function nachatSbrosPodgotovkiUnita(string $jobId): void
    {
        $this->validaziyaPeredSbrosomPodgotovkoi();
        $prozes = $this->poluchitProzesPoIdZadachi($jobId);
        $prozes->startJob($jobId);
        $state = $this->newState(new VOcherediNaSbrosPodgotovki());
        $this->recordThat(new SbrosPodgotovkiNachalsya($this->getId(), $jobId, $state->toArray($this), $prozes->toArray()));
    }

    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applySbrosPodgotovkiNachalsya(SbrosPodgotovkiNachalsya $fact): void
    {
        $prozess = UnitProcess::fromArray($fact->prozess);
        $this->obnovitProzes($prozess);
        $this->sbrosPodgotovki = $this->getJobById($fact->jobId);

        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    public function ustanovitOshibkuSbrosaPodgotovki(string $jobId, array $steps): void
    {
        $prozes = $this->poluchitProzesPoIdZadachi($jobId);
        $prozes->ustanovitResultatZadachi($jobId, false, $steps);
        $state = $this->newState(new OshibkaSbrosaPodgotovki());
        $this->recordThat(new OshibkaSbrosaPodgotovkiUnitaUstanovlena($this->getId(), $jobId, $state->toArray($this), $prozes->toArray()));
    }
    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyOshibkaSbrosaPodgotovkiUnitaUstanovlena(OshibkaSbrosaPodgotovkiUnitaUstanovlena $fact): void
    {
        $prozess = UnitProcess::fromArray($fact->prozess);
        $this->obnovitProzes($prozess);
        $this->sbrosPodgotovki = $this->getJobById($fact->jobId);
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    public function ustanovitUspehSbrosaPodgotovki(string $jobId, array $steps): void
    {
        $prozes = $this->poluchitProzesPoIdZadachi($jobId);
        $prozes->ustanovitResultatZadachi($jobId, true, $steps);

        $state = $this->newState(new Sobran());
        $this->recordThat(new UspehSbrosaPodgotovkiUnitaUstanovlen($this->getId(), $jobId, $state->toArray($this), $prozes->toArray()));
    }
    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyUspehSbrosaPodgotovkiUnitaUstanovlen(UspehSbrosaPodgotovkiUnitaUstanovlen $fact): void
    {
        $prozess = UnitProcess::fromArray($fact->prozess);
        $this->obnovitProzes($prozess);
        $this->sbrosPodgotovki = $this->getJobById($fact->jobId);
        $this->podgotovka = null;
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    //---------Запуск
    public function dobavitProzesZapuska(string $userId, string $prozesId): void
    {
        $this->validaziyaPeredZapuskom();
        $prozes = UnitProcess::make($prozesId, $userId,UnitProcess\UnitProcessType::ZAPUSK);
        $zadachi = [];
        $zadachi[] = RunnerJob::make( Uuid::uuid7()->toString(), RunnerJobType::ZAPUSK);

        $prozes->dobavitZadachiVProzess($zadachi);
        $this->dobavitProzes($prozes);
    }
    public function nachatZapuskUnita(string $jobId): void
    {
        $prozes = $this->poluchitProzesPoIdZadachi($jobId);
        $prozes->startJob($jobId);

        $state = $this->newState(new VOcherediNaZapusk());
        $this->recordThat(new ZapuskUnitNachalsya($this->getId(), $jobId, $state->toArray($this),$prozes->toArray()));
    }
    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyZapuskUnitNachalsya(ZapuskUnitNachalsya $fact): void
    {
        $prozess = UnitProcess::fromArray($fact->prozess);
        $this->obnovitProzes($prozess);
        $this->zapusk = $this->getJobById($fact->jobId);
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    public function ustanovitOshibkuZapuska(string $jobId, array $steps): void
    {
        $prozes = $this->poluchitProzesPoIdZadachi($jobId);
        $prozes->ustanovitResultatZadachi($jobId, false, $steps);

        $state = $this->newState(new OshibkaZapuska());
        $this->recordThat(new OshibkaZapuskaUnitaUstanovlena($this->getId(), $jobId, $state->toArray($this), $prozes->toArray()));
    }

    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyOshibkaZapuskaUnitaUstanovlena(OshibkaZapuskaUnitaUstanovlena $fact): void
    {
        $prozess = UnitProcess::fromArray($fact->prozess);
        $this->obnovitProzes($prozess);
        $this->zapusk = $this->getJobById($fact->jobId);
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    public function ustanovitUspehZapuska(string $jobId,array $steps): void
    {
        $prozes = $this->poluchitProzesPoIdZadachi($jobId);
        $prozes->ustanovitResultatZadachi($jobId, true, $steps);

        $state = $this->newState(new Zapushen());
        $this->recordThat(new UspehZapuskaUnitaUstanovlen($this->getId(), $jobId, $state->toArray($this), $prozes->toArray()));
    }
    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyUspehZapuskaUnitaUstanovlen(UspehZapuskaUnitaUstanovlen $fact): void
    {
        $prozess = UnitProcess::fromArray($fact->prozess);
        $this->obnovitProzes($prozess);
        $this->zapusk = $this->getJobById($fact->jobId);
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    public function validaziyaPeredOstanovkoi(): void
    {
        if ($this->isDeleted) {
            throw new DomainException('unit.udalen');
        }

        if ($this->isWaitResultFromRunner()) {
            throw new DomainException('unit.wait_runner');
        }

        if (!$this->zapusk || !$this->zapusk->isSuccess()) {
            throw new DomainException('unit.ne_zapushen');
        }
    }
    //---------Остановка
    public function dobavitProzesOstanovki(string $userId, string $prozesId): void
    {
        $this->validaziyaPeredOstanovkoi();
        $prozes = UnitProcess::make($prozesId,$userId, UnitProcess\UnitProcessType::OSTANOVKA);
        $zadachi = [];
        $zadachi[] = RunnerJob::make( Uuid::uuid7()->toString(), RunnerJobType::OSTANOVKA);

        $prozes->dobavitZadachiVProzess($zadachi);
        $this->dobavitProzes($prozes);
    }
    public function nachatOstanovkuUnita(string $jobId): void
    {
        $this->validaziyaPeredOstanovkoi();

        $prozes = $this->poluchitProzesPoIdZadachi($jobId);
        $prozes->startJob($jobId);

        $state = $this->newState(new VOcherediNaOstanovku());
        $this->recordThat(new OstanovkaUnitaNachalas($this->getId(),$jobId, $state->toArray($this), $prozes->toArray()));
    }
    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyOstanovkaUnitaNachalas(OstanovkaUnitaNachalas $fact): void
    {
        $prozess = UnitProcess::fromArray($fact->prozess);
        $this->obnovitProzes($prozess);
        $this->ostanovka = $this->getJobById($fact->jobId);
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    public function ustanovitOshibkuOstanovki(string $jobId, array $steps): void
    {
        $prozes = $this->poluchitProzesPoIdZadachi($jobId);
        $prozes->ustanovitResultatZadachi($jobId, false, $steps);

        $state = $this->newState(new OshibkaOstanovki());
        $this->recordThat(new OshibkaOstanovkiUnitaUstanovlena($this->getId(), $jobId, $state->toArray($this), $prozes->toArray()));
    }
    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyOshibkaOstanovkiUnitaUstanovlena(OshibkaOstanovkiUnitaUstanovlena $fact): void
    {
        $prozess = UnitProcess::fromArray($fact->prozess);
        $this->obnovitProzes($prozess);
        $this->ostanovka = $this->getJobById($fact->jobId);
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    public function ustanovitUspehOstanovki(string $jobId, array $steps): void
    {
        $prozes = $this->poluchitProzesPoIdZadachi($jobId);
        $prozes->ustanovitResultatZadachi($jobId, true, $steps);

        $state = $this->newState(new Podgotovlen());
        $this->recordThat(new UspehOstanovkiUnitaUstanovlen($this->getId(), $jobId, $state->toArray($this), $prozes->toArray()));
    }
    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyUspehOstanovkiUnitaUstanovlen(UspehOstanovkiUnitaUstanovlen $fact): void
    {
        $prozess = UnitProcess::fromArray($fact->prozess);
        $this->obnovitProzes($prozess);
        $this->ostanovka = $this->getJobById($fact->jobId);
        $this->zapusk = null;
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    //---------Удаление
    public function dobavitProzesUdaleniya(string $userId, string $prozesId): void
    {
        if ($this->isDeleted) {
            throw new DomainException('unit.udalen');
        }

        $prozes = UnitProcess::make($prozesId,$userId, UnitProcess\UnitProcessType::UDALENIE);
        $zadachi = [];
        if ($this->zapusk && $this->zapusk->isFinish()) {
            $zadachi[] = RunnerJob::make( Uuid::uuid7()->toString(), RunnerJobType::OSTANOVKA);
        }
        if ($this->podgotovka && $this->podgotovka->isFinish()) {
            $zadachi[] = RunnerJob::make(Uuid::uuid7()->toString(), RunnerJobType::SBROS_PODGOTOVKI);
        }
        $zadachi[] = RunnerJob::make(Uuid::uuid7()->toString(), RunnerJobType::UDALENIE);

        $prozes->dobavitZadachiVProzess($zadachi);
        $this->dobavitProzes($prozes);
    }
    public function nachatUdalenieUnita(string $jobId): void
    {
        $prozes = $this->poluchitProzesPoIdZadachi($jobId);
        $prozes->startJob($jobId);
        $state = new VOcherediNaUdalenie();
        $this->recordThat(new UdalenieUnitaNachalos($this->getId(), $jobId, $state->toArray($this), $prozes->toArray()));
    }
    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyUdalenieUnitaNachalos(UdalenieUnitaNachalos $fact): void
    {
        $prozess = UnitProcess::fromArray($fact->prozess);
        $this->obnovitProzes($prozess);
        $this->udalenie = $this->getJobById($fact->jobId);
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    public function ustanovitOshibkuUdaleniya(string $jobId, array $steps): void
    {
        $prozes = $this->poluchitProzesPoIdZadachi($jobId);
        $prozes->ustanovitResultatZadachi($jobId, false, $steps);

        $state = $this->newState(new Sloman());
        $this->recordThat(new OshibkaUdaleniyaUnitaUstanovlena($this->getId(), $jobId, $state->toArray($this), $prozes->toArray()));
    }
    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyOshibkaUdaleniyaUnitaUstanovlena(OshibkaUdaleniyaUnitaUstanovlena $fact): void
    {
        $prozess = UnitProcess::fromArray($fact->prozess);
        $this->obnovitProzes($prozess);
        $this->udalenie = $this->getJobById($fact->jobId);
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    public function ustanovitUspehUdaleniya(string $jobId, array $steps): void
    {
        $prozes = $this->poluchitProzesPoIdZadachi($jobId);
        $prozes->ustanovitResultatZadachi($jobId, true, $steps);

        $state = $this->newState(new Udalen());
        $this->recordThat(new UspehUdaleniyaUnitaUstanovlen($this->getId(), $jobId, $state->toArray($this), $prozes->toArray()));
    }
    /**
     * @psalm-suppress PossiblyNullReference
     */
    private function applyUspehUdaleniyaUnitaUstanovlen(UspehUdaleniyaUnitaUstanovlen $fact): void
    {
        $prozess = UnitProcess::fromArray($fact->prozess);
        $this->obnovitProzes($prozess);
        $this->udalenie = $this->getJobById($fact->jobId);
        $this->isDeleted = true;
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    public function udalitSlomaniyUnit(): void
    {
        if ($this->isDeleted) {
            throw new DomainException('unit.uge_udalen');
        }

        $state = $this->newState(new UdalenVruchnuyu());
        $this->recordThat(new SlomaniyUnitUdalen($this->getId(), $state->toArray($this)));
    }

    private function applySlomaniyUnitUdalen(SlomaniyUnitUdalen $fact): void
    {
        $this->isDeleted = true;
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
    }

    public function izmenitVremyaPoslednegoIzmeneniyaKodaVHranilishe(int $unixtime): void
    {
        if ($this->unixtimePoslednegoObnovleniyaKodaVHranilishe > $unixtime) {
            return;
        }
        $this->recordThat(new KodVetkiIzmenilsyaVHranilishe($this->getId(), $unixtime));
    }

    private function applyKodVetkiIzmenilsyaVHranilishe(KodVetkiIzmenilsyaVHranilishe $fact): void
    {
        $this->unixtimePoslednegoObnovleniyaKodaVHranilishe = $fact->unixtime;
    }

    public function getName(): string
    {
        if (empty($this->name)) {
            throw new DomainException('unit.name_not_found');
        }

        return (string) $this->name;
    }

    public function getBranch(): string
    {
        if (empty($this->branch)) {
            throw new DomainException('unit.branch_not_found');
        }

        return (string) $this->branch;
    }

    public function getProjectId(): string
    {
        if (empty($this->project)) {
            throw new DomainException('unit.project_not_found');
        }

        return $this->project->id;
    }

    public function poluchitKomandiPodgotovki(): array
    {
        return $this->configUnita?->getPrepare() ?? [];
    }

    public function poluchitKomandiSbrosaPodgotovki(): array
    {
        return $this->configUnita?->getResetPrepare() ?? [];
    }

    public function poluchitKomandiZapuska(): array
    {
        return $this->configUnita?->getUp() ?? [];
    }

    public function poluchitKomandiOstanovki(): array
    {
        return $this->configUnita?->getDown() ?? [];
    }

    /**
     * @return array<VariableValue>
     * */
    public function poluchitZnacheniyaPeremenih(): array
    {
        return $this->variableValues;
    }

    /**
     * @return KonfigServisa[]
     * */
    public function poluchitKonfigServisov(): array
    {
        return $this->configUnita?->getServices() ?? [];
    }

    /**
     * @return void
     */
    private function validateConfigValues(): void
    {
        $config = $this->checkIssetConfig();

        $errs = $config->validateValues($this->variableValues);

        if (!empty($errs)) {
            throw new DomainException(join(', ', $errs));
        }
    }


    function esliMognoObnovit(): bool
    {
        if ($this->isDeleted) return false;
        if ($this->isWaitResultFromRunner()) return false;
        if (empty($this->sborka)) return false;
        if (!empty($this->podgotovka)) return false;

        return true;
    }

    function getConfig(): ConfigUnita|null
    {
        return $this->configUnita;
    }

    function proverkaPrav(ProjectUser $projectUser): void
    {
        if (!$this->esliRazreshenoUpravlyatUnitom($projectUser)) {
            throw new DomainException('unit.ne_hvataet_prav');
        }
    }

    function poluchitDeistviePoJobId(string $jobId): array
    {
        if (empty($this->mapActions[$jobId])) {
            throw new \DomainException('unit.mapAction.not_found');
        }

        return $this->mapActions[$jobId];
    }

    /**
     * @psalm-ignore-nullable-return
     * */
    function getAuthorId(): ?string
    {
        return $this->authorId;
    }

    function esliNugnoObnovitKodUnita(): bool
    {
        return $this->unixtimePoslednegoObnovleniyaKodaVHranilishe > $this->unixtimePoslednegoObnovleniyaKodaUnita;
    }

    function poluchitKonfigDeistviya(string $actionId): ConfigUnita\KonfigDeistviya
    {
        $config = $this->checkIssetConfig();
        return $config->getActionById($actionId);
    }

    private function checkIssetConfig(): ConfigUnita
    {
        if (empty($this->configUnita)) {
            throw new DomainException('unit.config_unita_ne_opredelen');
        }

        return $this->configUnita;
    }

    public function koneinerUnitaNeZapushen(): void
    {
        if ($this->isDeleted) {
            throw new DomainException('unit.udalen');
        }

        if ($this->isWaitResultFromRunner()) {
            throw new DomainException('unit.wait_runner');
        }

        $state = new Sobran();

        $this->recordThat(new UnitSbroshenDoSostoyaniyaSborki($this->getId(), $state->toArray($this)));
    }

    private function applyUnitSbroshenDoSostoyaniyaSborki(UnitSbroshenDoSostoyaniyaSborki $fact): void
    {
        $this->state = StateFactory::makeByCode($fact->stateAsArray['code']);
        $this->zapusk = null;
        $this->podgotovka = null;
    }

    /**
     * @return void
     */
    public function proverkaVozmognostiObnovleniyaUnita(): void
    {
        if ($this->isDeleted) {
            throw new DomainException('unit.udalen');
        }

        if (empty($this->sborka)) {
            throw new DomainException('unit.sborka_ne_nachalas');
        }
    }

    /**
     * @return void
     */
    public function validaziyaPeredPodgotovkoi(): void
    {
        if ($this->isDeleted) {
            throw new DomainException('unit.udalen');
        }

        if ($this->isWaitResultFromRunner()) {
            throw new DomainException('unit.wait_runner');
        }

        if (!empty($this->podgotovka) && $this->podgotovka->isSuccess()) {
            throw new DomainException('unit.podgotovka_uge_bila');
        }

        if (empty($this->sborka)) {
            throw new DomainException('unit.sborka_ne_nachalas');
        }

        $this->validateConfigValues();
    }

    /**
     * @return void
     * @throws Exception
     */
    public function validaziyaPeredSbrosomPodgotovkoi(): void
    {
        $errors = [];

        if ($this->isDeleted) {
            $errors[] = 'unit.udalen';
        }

        if ($this->isWaitResultFromRunner()) {
            $errors[] = 'unit.wait_runner';
        }

        if ($this->zapusk && $this->zapusk->isSuccess()) {
            $errors[] = 'unit.zapushen';
        }

        if (empty($this->podgotovka)) {
            $errors[] = 'unit.podgotovka_ne_nachalas';
        }

        if (!empty($errors)) {
            throw new Exception($errors[0]);
        }

        $this->validateConfigValues();
    }

    /**
     * @return void
     */
    public function validaziyaPeredZapuskom(): void
    {
        if ($this->isDeleted) {
            throw new DomainException('unit.udalen');
        }

        if ($this->isWaitResultFromRunner()) {
            throw new DomainException('unit.wait_runner');
        }

        if ($this->zapusk && $this->zapusk->isSuccess()) {
            throw new DomainException('unit.zapushen');
        }

        if (empty($this->podgotovka)) {
            throw new DomainException('unit.podgotovka_ne_nachalas');
        }

        $this->validateConfigValues();
    }

    /**
     * @param string $prozesId
     * @return UnitProcess|null
     */
    private function naitiProzesPoId(string $prozesId): ?UnitProcess
    {
        $finded = null;

        foreach ($this->prozesi as $process) {
            if ($process->id === $prozesId) {
                $finded = $process;
            }
        }
        return $finded;
    }

    /**
     * @param string $prozesId
     * @return UnitProcess
     */
    private function poluchitProzesPoId(string $prozesId): UnitProcess
    {
        $finded = $this->naitiProzesPoId($prozesId);

        if (empty($finded)) {
            throw new DomainException('unit.prozess.not_found');
        }
        return $finded;
    }

    function poluchitJobuDlyObravotki(): RunnerJob | null
    {
        $result = null;

        foreach ($this->prozesi as $process) {
            if (!$process->isFinish()) {
                foreach ($process->jobs as $job) {
                    if (!$job->isFinish()) {
                        $result = $job;
                        break 2;
                    }
                }
            }
        }

        return $result;
    }
}
